<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function myIndex(Request $request)
    {
        $query = Shift::with(['outlet:id,name'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->orderBy('date')
            ->orderBy('start_time');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->isSupervisor()) {
            abort(403, 'Only owner/manager can list tenant shifts.');
        }

        $query = Shift::with(['user:id,name', 'outlet:id,name'])
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('date')
            ->orderBy('start_time');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user->isSupervisor()) {
            abort(403, 'Only owner/manager can create shifts.');
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = User::where('tenant_id', $user->tenant_id)->find($data['user_id']);
        if (! $employee) {
            throw ValidationException::withMessages(['user_id' => 'User not found for this tenant.']);
        }

        if ($data['outlet_id'] ?? null) {
            $outlet = Outlet::where('tenant_id', $user->tenant_id)->find($data['outlet_id']);
            if (! $outlet) {
                throw ValidationException::withMessages(['outlet_id' => 'Outlet not found for this tenant.']);
            }
        }

        $shift = Shift::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $data['user_id'],
            'outlet_id' => $data['outlet_id'] ?? $employee->outlet_id,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $shift], 201);
    }
}
