<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\Request;

class DiningTableController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('dining-tables.index', [
            'outlets' => $tenant ? $tenant->outlets()->where('is_active', true)->get() : collect(),
            'tables' => DiningTable::where('tenant_id', $request->user()->tenant_id)
                ->with('outlet')->orderBy('name')->paginate(50),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $outlet = $request->user()->tenant->outlets()->findOrFail($data['outlet_id']);
        DiningTable::create([
            'tenant_id' => $request->user()->tenant_id, 'outlet_id' => $outlet->id,
            'name' => $data['name'], 'status' => 'free', 'is_active' => true,
            'qr_code' => DiningTable::generateQrCode(),
        ]);

        return redirect()->route('dining-tables.index')->with('status', 'Meja ditambahkan. Cetak/tempel kode QR-nya di meja.');
    }

    public function edit(Request $request, DiningTable $table)
    {
        abort_if($table->tenant_id !== $request->user()->tenant_id, 404);

        return view('dining-tables.edit', ['table' => $table]);
    }

    public function update(Request $request, DiningTable $table)
    {
        abort_if($table->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $table->update(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false)]);

        return redirect()->route('dining-tables.index')->with('status', 'Meja diperbarui.');
    }

    public function regenerate(Request $request, DiningTable $table)
    {
        abort_if($table->tenant_id !== $request->user()->tenant_id, 404);
        $table->update(['qr_code' => DiningTable::generateQrCode()]);

        return redirect()->route('dining-tables.index')->with('status', 'Kode QR meja diperbarui — cetak ulang dan tempel yang baru, kode lama tidak berlaku lagi.');
    }
}
