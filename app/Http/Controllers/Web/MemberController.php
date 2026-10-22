<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $members = $tenant
            ? Member::where('tenant_id', $tenant->id)->orderBy('name')->get()
            : collect();
        $crm = app(CrmService::class);
        $members->each(fn (Member $m) => $m->setAttribute('points_balance', $crm->balanceFor($m)));

        return view('crm.members', ['members' => $members]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('members', 'phone')->where('tenant_id', $tenant->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'birthdate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        Member::create($data + ['tenant_id' => $tenant->id, 'is_active' => true]);

        return redirect()->route('crm.members')->with('status', 'Member ditambahkan.');
    }
}
