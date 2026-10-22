<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\Request;

class DiningTableController extends Controller
{
    public function index(Request $request)
    {
        $tables = DiningTable::where('tenant_id', $request->user()->tenant_id)
            ->with('outlet')
            ->when($request->filled('outlet_id'), fn ($q) => $q->where('outlet_id', $request->outlet_id))
            ->orderBy('name')
            ->paginate(50);

        return response()->json($tables);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        $data = $request->validate([
            'outlet_id' => ['required', 'exists:outlets,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $outlet = $request->user()->tenant->outlets()->findOrFail($data['outlet_id']);

        $table = DiningTable::create([
            'tenant_id' => $request->user()->tenant_id,
            'outlet_id' => $outlet->id,
            'name' => $data['name'],
            'status' => 'free',
            'is_active' => true,
            'qr_code' => DiningTable::generateQrCode(),
        ]);

        return response()->json(['data' => $table], 201);
    }

    public function update(Request $request, DiningTable $table)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($table->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $table->update($data);

        return response()->json(['data' => $table->fresh()]);
    }

    public function regenerate(Request $request, DiningTable $table)
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_if($table->tenant_id !== $request->user()->tenant_id, 404);
        $table->update(['qr_code' => DiningTable::generateQrCode()]);

        return response()->json(['data' => $table->fresh()]);
    }
}
