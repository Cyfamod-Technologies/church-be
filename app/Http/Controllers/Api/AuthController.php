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
            ])
            ->where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
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
            ],
        ]);
    }
}
