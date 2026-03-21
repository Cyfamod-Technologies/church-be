<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->with([
                'church',
                'branch.tag:id,name,slug',
                'branch.currentParentChurch:id,name,code',
                'branch.currentParentBranch:id,name',
                'homecellLeader.homecell.branch:id,name,code',
            ])
            ->where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $homecellLeader = $user->homecellLeader;
        $homecell = $homecellLeader?->homecell;

        if ($user->role === 'homecell_leader' && ! $homecellLeader) {
            return response()->json([
                'message' => 'This homecell leader account is not currently assigned to a homecell.',
            ], 422);
        }

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'church' => $user->church,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                    'status' => $user->branch->status,
                    'tag' => $user->branch->tag ? [
                        'id' => $user->branch->tag->id,
                        'name' => $user->branch->tag->name,
                        'slug' => $user->branch->tag->slug,
                    ] : null,
                    'current_parent' => $user->branch->currentParentBranch
                        ? [
                            'type' => 'branch',
                            'id' => $user->branch->currentParentBranch->id,
                            'name' => $user->branch->currentParentBranch->name,
                        ]
                        : ($user->branch->currentParentChurch ? [
                            'type' => 'church',
                            'id' => $user->branch->currentParentChurch->id,
                            'name' => $user->branch->currentParentChurch->name,
                            'code' => $user->branch->currentParentChurch->code,
                        ] : null),
                ] : null,
                'homecell_leader' => $homecellLeader ? [
                    'id' => $homecellLeader->id,
                    'name' => $homecellLeader->name,
                    'role' => $homecellLeader->role,
                    'phone' => $homecellLeader->phone,
                    'email' => $homecellLeader->email,
                    'is_primary' => $homecellLeader->is_primary,
                    'user_id' => $homecellLeader->user_id,
                ] : null,
                'homecell' => $homecell ? [
                    'id' => $homecell->id,
                    'name' => $homecell->name,
                    'code' => $homecell->code,
                    'meeting_day' => $homecell->meeting_day,
                    'meeting_time' => $homecell->meeting_time,
                    'branch' => $homecell->branch ? [
                        'id' => $homecell->branch->id,
                        'name' => $homecell->branch->name,
                        'code' => $homecell->branch->code,
                    ] : null,
                ] : null,
            ],
        ]);
    }
}
