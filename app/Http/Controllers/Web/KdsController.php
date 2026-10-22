<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TableOrder;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KdsController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $orders = TableOrder::where('tenant_id', $request->user()->tenant_id)
            ->whereIn('status', TableOrder::OPEN_STATUSES)
            ->with(['items', 'diningTable', 'outlet'])
            ->when($request->filled('outlet_id'), fn ($q) => $q->where('outlet_id', $request->outlet_id))
            ->orderBy('created_at')
            ->get()
            ->groupBy('status');

        return view('kds.index', [
            'outlets' => $tenant ? $tenant->outlets()->where('is_active', true)->get() : collect(),
            'selectedOutlet' => $request->outlet_id,
            'grouped' => $orders,
            'statuses' => TableOrder::OPEN_STATUSES,
        ]);
    }

    public function updateStatus(Request $request, TableOrder $order)
    {
        abort_if($order->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate(['status' => ['required', 'in:preparing,ready,served,cancelled']]);
        $allowed = TableOrder::TRANSITIONS[$order->status] ?? [];
        if (! in_array($data['status'], $allowed, true)) {
            return back()->withErrors(['status' => "Tidak bisa pindah dari {$order->status} ke {$data['status']}."]);
        }
        $order->update(['status' => $data['status']]);
        $order->diningTable->refreshStatus();

        return redirect()->route('kds.index', ['outlet_id' => $order->outlet_id])
            ->with('status', 'Status order #'.$order->id.' diubah ke '.$data['status'].'.');
    }

    public function convertForm(Request $request, TableOrder $order)
    {
        abort_if($order->tenant_id !== $request->user()->tenant_id, 404);
        abort_unless($order->isOpen(), 422, 'Hanya order yang masih terbuka yang bisa dibayar.');

        return view('kds.convert', ['order' => $order->load(['items', 'diningTable', 'outlet'])]);
    }

    public function convert(Request $request, TableOrder $order, SaleService $service)
    {
        abort_if($order->tenant_id !== $request->user()->tenant_id, 404);
        if ($order->status === 'paid' && $order->sale_id) {
            return redirect()->route('sales.show', $order->sale_id);
        }
        if (! $order->isOpen()) {
            return back()->withErrors(['order' => 'Hanya order yang masih terbuka yang bisa dibayar.']);
        }
        $data = $request->validate([
            'cash_amount' => ['nullable', 'integer', 'min:0'],
            'cash_tendered' => ['nullable', 'integer', 'min:0'],
            'edc_amount' => ['nullable', 'integer', 'min:0'],
            'edc_bank' => ['nullable', 'string', 'max:100'],
            'edc_card_type' => ['nullable', 'in:debit,credit'],
            'edc_approval_code' => ['nullable', 'string', 'max:100'],
        ]);
        $payments = [];
        if (($data['cash_amount'] ?? 0) > 0) {
            $payments[] = ['method' => 'cash', 'amount' => $data['cash_amount'], 'tendered' => $data['cash_tendered'] ?? $data['cash_amount']];
        }
        if (($data['edc_amount'] ?? 0) > 0) {
            $payments[] = ['method' => 'edc', 'amount' => $data['edc_amount'], 'bank' => $data['edc_bank'] ?? null, 'card_type' => $data['edc_card_type'] ?? null, 'approval_code' => $data['edc_approval_code'] ?? null];
        }
        if (empty($payments)) {
            return back()->withErrors(['payments' => 'Isi setidaknya satu pembayaran cash atau EDC.'])->withInput();
        }

        try {
            $sale = $service->createSale($request->user(), [
                'outlet_id' => $order->outlet_id,
                'items' => $order->items->map(fn ($i) => ['product_variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->all(),
                'payments' => $payments,
                'notes' => 'QR order #'.$order->id.' meja '.$order->diningTable->name,
                'client_reference' => 'qr-order-'.$order->id,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        DB::transaction(function () use ($order, $sale) {
            $order->update(['status' => 'paid', 'sale_id' => $sale->id]);
            $order->diningTable->refreshStatus();
        });

        return redirect()->route('sales.show', $sale)->with('status', 'Order #'.$order->id.' dibayar sebagai '.$sale->sale_number.'.');
    }
}
