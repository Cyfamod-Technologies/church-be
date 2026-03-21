<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChurchUnitRequest;
use App\Http\Requests\Api\UpdateChurchUnitRequest;
use App\Models\ChurchUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChurchUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $churchId = $request->integer('church_id');
        $search = trim($request->string('search')->toString());
        $status = trim($request->string('status')->toString());

        $query = ChurchUnit::query()
            ->withCount('members')
            ->when($churchId, fn (Builder $builder) => $builder->where('church_id', $churchId))
            ->when($search !== '', fn (Builder $builder) => $builder->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->orderBy('name');

        $units = $query->get();

        return response()->json([
            'data' => $units->map(fn (ChurchUnit $unit) => $this->transformUnit($unit)),
            'meta' => [
                'stats' => [
                    'total_units' => $units->count(),
                    'active_units' => $units->where('status', 'active')->count(),
                    'inactive_units' => $units->where('status', '!=', 'active')->count(),
                    'member_assignments' => $units->sum('members_count'),
                ],
            ],
        ]);
    }

    public function store(StoreChurchUnitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $unit = ChurchUnit::create([
            'church_id' => $validated['church_id'],
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: $this->generateCode($validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ])->loadCount('members');

        return response()->json([
            'message' => 'Church unit created successfully.',
            'data' => $this->transformUnit($unit),
        ], 201);
    }

    public function show(ChurchUnit $churchUnit): JsonResponse
    {
        $churchUnit->loadCount('members');

        return response()->json([
            'data' => $this->transformUnit($churchUnit),
        ]);
    }

    public function update(UpdateChurchUnitRequest $request, ChurchUnit $churchUnit): JsonResponse
    {
        $validated = $request->validated();

        $churchUnit->update([
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: $churchUnit->code ?: $this->generateCode($validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $churchUnit->status,
        ]);

        $churchUnit->loadCount('members');

        return response()->json([
            'message' => 'Church unit updated successfully.',
            'data' => $this->transformUnit($churchUnit),
        ]);
    }

    private function transformUnit(ChurchUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'church_id' => $unit->church_id,
            'name' => $unit->name,
            'code' => $unit->code,
            'description' => $unit->description,
            'status' => $unit->status,
            'members_count' => (int) ($unit->members_count ?? 0),
            'created_at' => $unit->created_at,
            'updated_at' => $unit->updated_at,
        ];
    }

    private function generateCode(string $name): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8));

        return trim($prefix ?: 'UNIT').'-'.Str::upper(Str::random(5));
    }
}
