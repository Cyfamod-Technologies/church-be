<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        $states = State::query()
            ->withCount('localGovernmentAreas')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => $states,
        ]);
    }

    public function lgas(Request $request): JsonResponse
    {
        $request->validate([
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'state_slug' => ['nullable', 'string', 'exists:states,slug'],
        ]);

        $lgas = LocalGovernmentArea::query()
            ->with('state:id,name,slug')
            ->when($request->filled('state_id'), fn ($query) => $query->where('state_id', $request->integer('state_id')))
            ->when($request->filled('state_slug'), function ($query) use ($request) {
                $stateSlug = $request->string('state_slug')->toString();

                $query->whereHas('state', fn ($stateQuery) => $stateQuery->where('slug', $stateSlug));
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $lgas,
        ]);
    }

    public function stateLgas(State $state): JsonResponse
    {
        return response()->json([
            'data' => $state->localGovernmentAreas()->get(),
        ]);
    }
}
