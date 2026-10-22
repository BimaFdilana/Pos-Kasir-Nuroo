<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    private function scope(Request $request, bool $supervisorOnly = false)
    {
        $user = $request->user();
        $query = Attendance::with(['user:id,name', 'outlet:id,name'])
            ->where('tenant_id', $user->tenant_id);

        if ($supervisorOnly) {
            if (! $user->isSupervisor()) {
                abort(403, 'Only owner/manager can list tenant attendance.');
            }
        } else {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public function open(Request $request)
    {
        $open = $this->scope($request)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        return response()->json(['data' => $open]);
    }

    public function myIndex(Request $request)
    {
        $query = $this->scope($request)->latest('checked_in_at');

        if ($request->filled('date')) {
            $query->whereDate('checked_in_at', $request->date);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function index(Request $request)
    {
        $query = $this->scope($request, true)->latest('checked_in_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }
        if ($request->filled('date')) {
            $query->whereDate('checked_in_at', $request->date);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function checkIn(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'outlet_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['outlet_id'] ?? null) {
            $outlet = Outlet::where('tenant_id', $user->tenant_id)->find($data['outlet_id']);
            if (! $outlet) {
                throw ValidationException::withMessages(['outlet_id' => 'Outlet not found for this tenant.']);
            }
        }

        $open = Attendance::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->first();

        if ($open) {
            throw ValidationException::withMessages(['attendance' => 'You already have an open check-in. Check out first.']);
        }

        $attendance = Attendance::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'outlet_id' => $data['outlet_id'] ?? $user->outlet_id,
            'checked_in_at' => now(),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $attendance], 201);
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $open = Attendance::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if (! $open) {
            throw ValidationException::withMessages(['attendance' => 'No open check-in to check out from.']);
        }

        $open->update([
            'checked_out_at' => now(),
            'checkout_latitude' => $data['latitude'] ?? null,
            'checkout_longitude' => $data['longitude'] ?? null,
            'checkout_note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $open->fresh()]);
    }
}
