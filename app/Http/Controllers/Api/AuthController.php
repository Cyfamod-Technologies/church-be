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
            ->with('church')
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
            ],
        ]);
    }
}
