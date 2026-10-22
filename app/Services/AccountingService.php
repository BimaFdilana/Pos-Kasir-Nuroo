<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Core accounting: a small fixed chart of accounts, one journal entry
 * per completed sale, and one reversing entry per void/refund.
 *
 * Balancing rule for a sale (all integer rupiah):
 *   debit  Kas                  = cash payments
 *   debit  Piutang EDC          = EDC payments
 *   debit  Diskon Penjualan    = discount_total (manual + voucher + points)
 *   debit  Harga Pokok Penjualan = cogs_total (0 when no recipe)
 *   credit Pendapatan Penjualan = subtotal + tax_total
 *   credit Persediaan Bahan Baku = cogs_total
 * Cash + EDC payments always equal the sale total, and the total is
 * subtotal - discount + tax, so debits always equal credits. The
 * method verifies that and refuses to write an unbalanced entry.
 *
 * Profit & loss nets each account over its journal lines, so a
 * voided/refunded sale (reversing entry) nets to zero in every total.
 * This is core accounting only: no tax reporting, no full double-entry
 * ledger beyond sales, no payroll.
 */
class AccountingService
{
    public const ACCOUNTS = [
        '1101' => ['Kas', 'asset'],
        '1102' => ['Piutang EDC', 'asset'],
        '1301' => ['Persediaan Bahan Baku', 'asset'],
        '4101' => ['Pendapatan Penjualan', 'revenue'],
        '4201' => ['Diskon Penjualan', 'contra_revenue'],
        '5101' => ['Harga Pokok Penjualan', 'expense'],
    ];

    /** @return array<string, Account> keyed by code */
    public function ensureAccounts(Tenant $tenant): array
    {
        $accounts = [];
        foreach (self::ACCOUNTS as $code => [$name, $type]) {
            $accounts[$code] = Account::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['name' => $name, 'type' => $type],
            );
        }

        return $accounts;
    }

    public function recordForSale(Sale $sale): ?JournalEntry
    {
        $sale->loadMissing(['payments']);
        $accounts = $this->ensureAccounts($sale->tenant);

        $cash = (int) $sale->payments->where('method', 'cash')->sum('amount');
        $edc = (int) $sale->payments->where('method', 'edc')->sum('amount');
        $discount = (int) $sale->discount_total;
        $cogs = (int) $sale->cogs_total;
        $revenue = (int) $sale->subtotal + (int) $sale->tax_total;

        // [account code, debit, credit] — zero lines are skipped.
        $lines = [
            ['1101', $cash, 0],
            ['1102', $edc, 0],
            ['4201', $discount, 0],
            ['5101', $cogs, 0],
            ['4101', 0, $revenue],
            ['1301', 0, $cogs],
        ];

        $debits = collect($lines)->sum(fn ($l) => $l[1]);
        $credits = collect($lines)->sum(fn ($l) => $l[2]);
        if ($debits !== $credits) {
            throw new \LogicException("Unbalanced journal for sale {$sale->sale_number}: debits {$debits} != credits {$credits}.");
        }

        $entry = JournalEntry::create([
            'tenant_id' => $sale->tenant_id, 'outlet_id' => $sale->outlet_id,
            'sale_id' => $sale->id, 'user_id' => $sale->user_id,
            'entry_date' => $sale->created_at?->toDateString() ?? today()->toDateString(),
            'description' => 'Penjualan '.$sale->sale_number,
            'reference' => $sale->sale_number, 'is_reversal' => false,
        ]);

        foreach ($lines as [$code, $debit, $credit]) {
            if ($debit === 0 && $credit === 0) {
                continue;
            }
            JournalLine::create([
                'journal_entry_id' => $entry->id, 'account_id' => $accounts[$code]->id,
                'debit' => $debit, 'credit' => $credit,
            ]);
        }

        return $entry;
    }

    /** Reversing entry for a void/refund: every line swapped debit/credit. */
    public function reverseForSale(User $supervisor, Sale $sale): ?JournalEntry
    {
        $original = JournalEntry::where('sale_id', $sale->id)
            ->where('is_reversal', false)->with('lines')->first();
        if (! $original) {
            return null;
        }
        if (JournalEntry::where('sale_id', $sale->id)->where('is_reversal', true)->exists()) {
            return null;
        }

        $entry = JournalEntry::create([
            'tenant_id' => $sale->tenant_id, 'outlet_id' => $sale->outlet_id,
            'sale_id' => $sale->id, 'user_id' => $supervisor->id,
            'entry_date' => today()->toDateString(),
            'description' => 'Pembatalan penjualan '.$sale->sale_number,
            'reference' => $sale->sale_number, 'is_reversal' => true,
        ]);

        foreach ($original->lines as $line) {
            JournalLine::create([
                'journal_entry_id' => $entry->id, 'account_id' => $line->account_id,
                'debit' => $line->credit, 'credit' => $line->debit,
            ]);
        }

        return $entry;
    }

    /** Net profit & loss for a tenant/outlet/date range (reversals net out). */
    public function profitLoss(Tenant $tenant, ?int $outletId, ?string $from, ?string $to): array
    {
        $net = function (string $code, string $direction) use ($tenant, $outletId, $from, $to): int {
            $q = JournalLine::join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.tenant_id', $tenant->id)
                ->where('accounts.code', $code)
                ->when($outletId, fn ($q) => $q->where('journal_entries.outlet_id', $outletId))
                ->when($from, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $to));

            // clone the query before aggregating each side
            $debit = (clone $q)->sum('journal_lines.debit');
            $credit = (clone $q)->sum('journal_lines.credit');

            return $direction === 'credit' ? (int) ($credit - $debit) : (int) ($debit - $credit);
        };

        $revenue = $net('4101', 'credit');
        $discounts = $net('4201', 'debit');
        $cogs = $net('5101', 'debit');

        return [
            'revenue' => $revenue,
            'discounts' => $discounts,
            'cogs' => $cogs,
            'gross_profit' => $revenue - $discounts - $cogs,
        ];
    }
}
