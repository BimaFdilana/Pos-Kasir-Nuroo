<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /** Any role of the tenant may manage members; cross-tenant is 404. */
    protected function scope(Request $request)
    {
        return Member::where('tenant_id', $request->user()->tenant_id);
    }

    protected function withBalance(Member $member): array
    {
        return array_merge($member->toArray(), [
            'points_balance' => app(CrmService::class)->balanceFor($member),
        ]);
    }

    public function index(Request $request)
    {
        $members = $this->scope($request)->with('pointLedgers')->orderBy('name')->paginate(50);
        $members->getCollection()->transform(fn (Member $m) => $this->withBalance($m));

        return response()->json($members);
    }

    public function show(Request $request, Member $member)
    {
        abort_if($member->tenant_id !== $request->user()->tenant_id, 404);

        return response()->json(['data' => $this->withBalance($member->load('pointLedgers'))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('members', 'phone')->where('tenant_id', $request->user()->tenant_id)],
            'email' => ['nullable', 'email', 'max:150'],
            'birthdate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $member = Member::create($data + ['tenant_id' => $request->user()->tenant_id]);

        return response()->json(['data' => $this->withBalance($member)], 201);
    }

    public function update(Request $request, Member $member)
    {
        abort_if($member->tenant_id !== $request->user()->tenant_id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:30', Rule::unique('members', 'phone')->where('tenant_id', $request->user()->tenant_id)->ignore($member->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'birthdate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $member->update($data);

        return response()->json(['data' => $this->withBalance($member->fresh())]);
    }

    public function destroy(Request $request, Member $member)
    {
        abort_if($member->tenant_id !== $request->user()->tenant_id, 404);
        if ($member->sales()->exists() || $member->pointLedgers()->exists()) {
            return response()->json(['message' => 'Member cannot be deleted: it has sales or points history. Deactivate it instead.'], 422);
        }
        $member->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
