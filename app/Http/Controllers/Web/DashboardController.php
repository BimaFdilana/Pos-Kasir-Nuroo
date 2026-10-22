<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $today = Sale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('created_at', today());

        $products = $user->tenant
            ? $user->tenant->products()->with(['category', 'variants'])->latest()->limit(50)->get()
            : collect();
        $outlets = $user->tenant
            ? $user->tenant->outlets()->latest()->get()
            : collect();
        $sales = Sale::where('tenant_id', $tenantId)
            ->with(['outlet', 'cashier', 'payments'])
            ->latest()
            ->limit(50)
            ->get();

        return view('dashboard', [
            'tenant' => $user->tenant,
            'products' => $products,
            'outlets' => $outlets,
            'sales' => $sales,
            'todayCount' => (clone $today)->count(),
            'todayTotal' => (clone $today)->sum('total'),
            // Sum of cash payment amounts (not a count of sales containing cash).
            'todayCash' => SalePayment::where('method', 'cash')
                ->whereHas('sale', fn ($q) => $q->where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->whereDate('created_at', today()))
                ->sum('amount'),
        ]);
    }
}
