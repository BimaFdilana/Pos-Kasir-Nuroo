<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Product;

class QrGuestController extends Controller
{
    /** Public page a customer lands on after scanning the table QR code. */
    public function show(string $code)
    {
        $table = DiningTable::where('qr_code', $code)->where('is_active', true)->firstOrFail();
        $products = Product::where('tenant_id', $table->tenant_id)
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('qr.order', ['table' => $table, 'products' => $products]);
    }
}
