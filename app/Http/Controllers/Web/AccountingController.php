<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function journals(Request $request)
    {
        $tenant = $request->user()->tenant;
        $entries = $tenant ? JournalEntry::where('tenant_id', $tenant->id)
            ->with(['lines.account', 'outlet'])
            ->when($request->filled('outlet_id'), fn ($q) => $q->where('outlet_id', $request->outlet_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->latest('entry_date')->latest('id')->paginate(50) : collect();

        return view('accounting.journals', [
            'entries' => $entries,
            'outlets' => $tenant ? $tenant->outlets()->orderBy('name')->get() : collect(),
        ]);
    }

    public function profitLoss(Request $request, AccountingService $service)
    {
        $tenant = $request->user()->tenant;

        return view('accounting.profit-loss', [
            'summary' => $tenant ? $service->profitLoss(
                $tenant,
                $request->filled('outlet_id') ? (int) $request->outlet_id : null,
                $request->from, $request->to,
            ) : ['revenue' => 0, 'discounts' => 0, 'cogs' => 0, 'gross_profit' => 0],
            'outlets' => $tenant ? $tenant->outlets()->orderBy('name')->get() : collect(),
        ]);
    }
}
