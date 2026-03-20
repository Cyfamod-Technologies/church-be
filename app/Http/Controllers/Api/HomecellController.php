<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHomecellRequest;
use App\Http\Requests\Api\UpdateHomecellRequest;
use App\Models\Branch;
use App\Models\Homecell;
use App\Models\HomecellLeader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomecellController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $churchId = $request->integer('church_id');
        $branchId = $request->integer('branch_id');
        $search = $request->string('search')->toString();

        $query = Homecell::query()
            ->with($this->relations())
            ->when($churchId, fn (Builder $builder) => $builder->where('church_id', $churchId))
            ->when($branchId, fn (Builder $builder) => $builder->where('branch_id', $branchId))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city_area', 'like', '%'.$search.'%')
                        ->orWhere('host_name', 'like', '%'.$search.'%')
                        ->orWhereHas('leaders', fn (Builder $leaderQuery) => $leaderQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('name');

        $homecells = $query->get();

        return response()->json([
            'data' => $homecells->map(fn (Homecell $homecell) => $this->transformHomecell($homecell)),
            'meta' => [
                'stats' => [
                    'total_homecells' => $homecells->count(),
                    'assigned_to_branches' => $homecells->whereNotNull('branch_id')->count(),
                    'unassigned_homecells' => $homecells->whereNull('branch_id')->count(),
                    'leaders_assigned' => $homecells->sum(fn (Homecell $homecell) => $homecell->leaders->count()),
                ],
            ],
        ]);
    }

    public function store(StoreHomecellRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $homecell = DB::transaction(function () use ($validated): Homecell {
            $homecell = Homecell::create([
                'church_id' => $validated['church_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'name' => $validated['name'],
                'code' => ($validated['code'] ?? null) ?: $this->generateCode($validated['name']),
                'meeting_day' => $validated['meeting_day'] ?? null,
                'meeting_time' => $validated['meeting_time'] ?? null,
                'host_name' => $validated['host_name'] ?? null,
                'host_phone' => $validated['host_phone'] ?? null,
                'city_area' => $validated['city_area'] ?? null,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncLeaders($homecell, $validated['leaders'] ?? []);

            return $homecell->fresh($this->relations());
        });

        return response()->json([
            'message' => 'Homecell created successfully.',
            'data' => $this->transformHomecell($homecell),
        ], 201);
    }

    public function show(Homecell $homecell): JsonResponse
    {
        $homecell->load($this->relations());

        return response()->json([
            'data' => $this->transformHomecell($homecell),
        ]);
    }

    public function update(UpdateHomecellRequest $request, Homecell $homecell): JsonResponse
    {
        $validated = $request->validated();

        $homecell = DB::transaction(function () use ($validated, $homecell): Homecell {
            $homecell->update([
                'branch_id' => $validated['branch_id'] ?? null,
                'name' => $validated['name'],
                'code' => ($validated['code'] ?? null) ?: $homecell->code ?: $this->generateCode($validated['name']),
                'meeting_day' => $validated['meeting_day'] ?? null,
                'meeting_time' => $validated['meeting_time'] ?? null,
                'host_name' => $validated['host_name'] ?? null,
                'host_phone' => $validated['host_phone'] ?? null,
                'city_area' => $validated['city_area'] ?? null,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? $homecell->status,
            ]);

            $this->syncLeaders($homecell, $validated['leaders'] ?? []);

            return $homecell->fresh($this->relations());
        });

        return response()->json([
            'message' => 'Homecell updated successfully.',
            'data' => $this->transformHomecell($homecell),
        ]);
    }

    private function relations(): array
    {
        return [
            'church:id,name,code',
            'branch:id,name,code',
            'leaders:id,homecell_id,name,role,phone,email,is_primary,sort_order',
        ];
    }

    private function transformHomecell(Homecell $homecell): array
    {
        return [
            'id' => $homecell->id,
            'name' => $homecell->name,
            'code' => $homecell->code,
            'meeting_day' => $homecell->meeting_day,
            'meeting_time' => $homecell->meeting_time,
            'host_name' => $homecell->host_name,
            'host_phone' => $homecell->host_phone,
            'city_area' => $homecell->city_area,
            'address' => $homecell->address,
            'notes' => $homecell->notes,
            'status' => $homecell->status,
            'church' => $homecell->church ? [
                'id' => $homecell->church->id,
                'name' => $homecell->church->name,
                'code' => $homecell->church->code,
            ] : null,
            'branch' => $homecell->branch ? [
                'id' => $homecell->branch->id,
                'name' => $homecell->branch->name,
                'code' => $homecell->branch->code,
            ] : null,
            'leaders' => $homecell->leaders->map(fn (HomecellLeader $leader) => [
                'id' => $leader->id,
                'name' => $leader->name,
                'role' => $leader->role,
                'phone' => $leader->phone,
                'email' => $leader->email,
                'is_primary' => $leader->is_primary,
            ])->values(),
            'created_at' => $homecell->created_at,
            'updated_at' => $homecell->updated_at,
        ];
    }

    private function syncLeaders(Homecell $homecell, array $leaders): void
    {
        $homecell->leaders()->delete();

        foreach (array_values($leaders) as $index => $leader) {
            HomecellLeader::create([
                'homecell_id' => $homecell->id,
                'name' => $leader['name'],
                'role' => $leader['role'] ?? 'Leader',
                'phone' => $leader['phone'] ?? null,
                'email' => $leader['email'] ?? null,
                'is_primary' => (bool) ($leader['is_primary'] ?? false),
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function generateCode(string $name): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8));

        return trim($prefix ?: 'HC').'-'.Str::upper(Str::random(6));
    }
}
