<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateHomecellLeaderProfileRequest;
use App\Models\HomecellLeader;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HomecellLeaderController extends Controller
{
    public function show(HomecellLeader $homecellLeader): JsonResponse
    {
        return response()->json([
            'data' => $this->transformLeader($homecellLeader->load([
                'user:id,name,email,phone,role,church_id,branch_id',
                'homecell.branch:id,name,code',
            ])),
        ]);
    }

    public function update(UpdateHomecellLeaderProfileRequest $request, HomecellLeader $homecellLeader): JsonResponse
    {
        $validated = $request->validated();

        $leader = DB::transaction(function () use ($validated, $homecellLeader): HomecellLeader {
            $homecellLeader->fill([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
            ]);
            $homecellLeader->save();

            $user = $homecellLeader->user ?: new User([
                'church_id' => $homecellLeader->homecell->church_id,
                'branch_id' => $homecellLeader->homecell->branch_id,
                'role' => 'homecell_leader',
            ]);

            $user->fill([
                'church_id' => $homecellLeader->homecell->church_id,
                'branch_id' => $homecellLeader->homecell->branch_id,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'role' => 'homecell_leader',
            ]);

            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();

            if ($homecellLeader->user_id !== $user->id) {
                $homecellLeader->user_id = $user->id;
                $homecellLeader->save();
            }

            return $homecellLeader->fresh([
                'user:id,name,email,phone,role,church_id,branch_id',
                'homecell.branch:id,name,code',
            ]);
        });

        return response()->json([
            'message' => 'Homecell leader profile updated successfully.',
            'data' => $this->transformLeader($leader),
        ]);
    }

    private function transformLeader(HomecellLeader $leader): array
    {
        return [
            'id' => $leader->id,
            'user_id' => $leader->user_id,
            'name' => $leader->name,
            'role' => $leader->role,
            'phone' => $leader->phone,
            'email' => $leader->email,
            'is_primary' => $leader->is_primary,
            'can_login' => (bool) $leader->user_id,
            'login_account' => $leader->user ? [
                'id' => $leader->user->id,
                'name' => $leader->user->name,
                'email' => $leader->user->email,
                'phone' => $leader->user->phone,
                'role' => $leader->user->role,
            ] : null,
            'homecell' => $leader->homecell ? [
                'id' => $leader->homecell->id,
                'name' => $leader->homecell->name,
                'code' => $leader->homecell->code,
                'branch' => $leader->homecell->branch ? [
                    'id' => $leader->homecell->branch->id,
                    'name' => $leader->homecell->branch->name,
                    'code' => $leader->homecell->branch->code,
                ] : null,
            ] : null,
        ];
    }
}
