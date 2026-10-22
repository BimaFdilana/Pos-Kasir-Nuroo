<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        return view('outlets.index', [
            'outlets' => $tenant ? $tenant->outlets()->latest()->paginate(50) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
        ]);

        $tenant->outlets()->create($data);

        return redirect()->route('outlets.index')->with('status', 'Outlet ditambahkan.');
    }

    public function edit(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $outlet = $tenant->outlets()->findOrFail($id);

        return view('outlets.edit', ['outlet' => $outlet]);
    }

    public function update(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $outlet = $tenant->outlets()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $outlet->update($data);

        return redirect()->route('outlets.index')->with('status', 'Outlet diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $tenant = $request->user()->tenant;
        $outlet = $tenant->outlets()->findOrFail($id);

        // Sales history must survive: archive instead of hard-deleting
        // an outlet that has sales (deleting it would cascade-delete them).
        if ($outlet->sales()->exists()) {
            $outlet->update(['is_active' => false]);

            return redirect()->route('outlets.index')->with('status', 'Outlet dinonaktifkan karena sudah ada riwayat penjualan; data penjualan tidak dihapus.');
        }

        $outlet->delete();

        return redirect()->route('outlets.index')->with('status', 'Outlet dihapus.');
    }
}
