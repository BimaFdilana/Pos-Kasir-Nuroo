<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = Sale::where('tenant_id', $request->user()->tenant_id)
            ->with(['outlet', 'cashier', 'payments'])
            ->latest()
            ->paginate(50);

        return view('sales.index', ['sales' => $sales]);
    }

    public function show(Request $request, Sale $sale)
    {
        abort_if($sale->tenant_id !== $request->user()->tenant_id, 404);

        return view('sales.show', ['sale' => $sale->load(['items', 'payments', 'audits.actor', 'outlet', 'cashier'])]);
    }

    public function create(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('sales.create', [
            'outlets' => $tenant ? $tenant->outlets()->where('is_active', true)->get() : collect(),
            'variants' => $tenant
                ? $tenant->products()->with('variants')->where('is_active', true)->get()
                    ->flatMap(fn ($p) => $p->variants->map(fn ($v) => ['id' => $v->id, 'price' => $v->price, 'label' => $p->name.' - '.$v->name.' (Rp '.number_format($v->price, 0, ',', '.').')']))
                : collect(),
        ]);
    }

    public function store(Request $request, SaleService $service)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,edc'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.tendered' => ['nullable', 'integer', 'min:0'],
            'payments.*.bank' => ['nullable', 'string', 'max:100'],
            'payments.*.card_type' => ['nullable', 'in:debit,credit'],
            'payments.*.approval_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $sale = $service->createSale($request->user(), $data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Penjualan berhasil: '.$sale->sale_number);
    }

    public function void(Request $request, Sale $sale, SaleService $service)
    {
        abort_if($sale->tenant_id !== $request->user()->tenant_id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $service->voidSale($request->user(), $sale, $data['reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Penjualan dibatalkan (void) dan tercatat di audit.');
    }

    public function refund(Request $request, Sale $sale, SaleService $service)
    {
        abort_if($sale->tenant_id !== $request->user()->tenant_id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $service->refundSale($request->user(), $sale, $data['reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Penjualan di-refund penuh dan tercatat di audit.');
    }
}
