<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBranchTagRequest;
use App\Models\BranchTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BranchTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        BranchTag::ensureDefaults();

        $churchId = $request->integer('church_id');

        $tags = BranchTag::query()
            ->when($churchId, function ($query) use ($churchId) {
                $query->where(function ($innerQuery) use ($churchId) {
                    $innerQuery->whereNull('church_id')
                        ->orWhere('church_id', $churchId);
                });
            })
            ->orderByRaw('church_id is null desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function store(StoreBranchTagRequest $request): JsonResponse
    {
        BranchTag::ensureDefaults();

        $validated = $request->validated();

        $tag = BranchTag::create([
            'church_id' => $validated['church_id'] ?? null,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return response()->json([
            'message' => 'Branch tag created successfully.',
            'data' => $tag,
        ], 201);
    }

    public function destroy(BranchTag $branchTag): JsonResponse
    {
        if ($branchTag->church_id === null) {
            return response()->json([
                'message' => 'Default tags cannot be deleted.',
            ], 422);
        }

        if ($branchTag->branches()->exists()) {
            return response()->json([
                'message' => 'This tag is already assigned to branches and cannot be deleted.',
            ], 422);
        }

        $branchTag->delete();

        return response()->json([
            'message' => 'Branch tag deleted successfully.',
        ]);
    }
}
