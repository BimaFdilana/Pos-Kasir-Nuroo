<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function accounts(Request $request, AccountingService $service)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return response()->json(['data' => array_values($service->ensureAccounts($request->user()->tenant))]);
    }

    public function journals(Request $request)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        $entries = JournalEntry::where('tenant_id', $request->user()->tenant_id)
            ->with(['lines.account', 'outlet', 'sale'])
            ->when($request->filled('outlet_id'), fn ($q) => $q->where('outlet_id', $request->outlet_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->latest('entry_date')->latest('id')
            ->paginate(50);

        return response()->json($entries);
    }

    public function profitLoss(Request $request, AccountingService $service)
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return response()->json(['data' => $service->profitLoss(
            $request->user()->tenant,
            $request->filled('outlet_id') ? (int) $request->outlet_id : null,
            $request->from, $request->to,
        )]);
    }
}
