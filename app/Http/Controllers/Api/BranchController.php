<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReassignBranchRequest;
use App\Http\Requests\Api\StoreBranchRequest;
use App\Http\Requests\Api\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\BranchAssignmentHistory;
use App\Models\BranchTag;
use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        BranchTag::ensureDefaults();

        $churchId = $request->integer('church_id');
        $tag = $request->string('tag')->toString();
        $search = $request->string('search')->toString();

        $query = Branch::query()
            ->with($this->branchRelations(false))
            ->when($churchId, fn (Builder $builder) => $builder->where('created_by_church_id', $churchId))
            ->when($tag !== '', function (Builder $builder) use ($tag) {
                $builder->whereHas('tag', function (Builder $tagQuery) use ($tag) {
                    $tagQuery->where('slug', $tag)->orWhere('id', $tag);
                });
            })
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('state', 'like', '%'.$search.'%')
                        ->orWhere('pastor_name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');

        $branches = $query->get();

        return response()->json([
            'data' => $branches->map(fn (Branch $branch) => $this->transformBranch($branch, false)),
            'meta' => [
                'stats' => [
                    'total_branches' => $branches->count(),
                    'direct_branches' => $churchId ? $branches->where('current_parent_church_id', $churchId)->count() : $branches->whereNotNull('current_parent_church_id')->count(),
                    'sub_branches' => $branches->whereNotNull('current_parent_branch_id')->count(),
                ],
            ],
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        BranchTag::ensureDefaults();

        $validated = $request->validated();

        $branch = DB::transaction(function () use ($validated): Branch {
            $branch = Branch::create([
                'name' => $validated['name'],
                'code' => ($validated['code'] ?? null) ?: $this->generateBranchCode($validated['name']),
                'branch_tag_id' => $validated['branch_tag_id'],
                'pastor_name' => $validated['pastor_name'] ?? null,
                'pastor_phone' => $validated['pastor_phone'] ?? null,
                'pastor_email' => $validated['pastor_email'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'district_area' => $validated['district_area'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'created_by_church_id' => $validated['created_by_church_id'],
                'created_by_user_id' => $validated['created_by_user_id'] ?? null,
                'created_by_actor_type' => $validated['created_by_actor_type'],
                'current_parent_church_id' => $validated['created_by_church_id'],
                'current_parent_branch_id' => null,
                'last_assigned_by_church_id' => $validated['created_by_church_id'],
                'last_assigned_by_user_id' => $validated['created_by_user_id'] ?? null,
                'last_assigned_actor_type' => $validated['created_by_actor_type'],
            ]);

            $this->recordAssignmentHistory(
                branch: $branch,
                actionType: 'create',
                fromChurchId: null,
                fromBranchId: null,
                toChurchId: $validated['created_by_church_id'],
                toBranchId: null,
                changedByChurchId: $validated['created_by_church_id'],
                changedByUserId: $validated['created_by_user_id'] ?? null,
                changedByActorType: $validated['created_by_actor_type'],
                note: 'Branch created under creator church.',
            );

            $this->syncLocalAdmin($branch, $validated['admin'] ?? null);

            return $branch->fresh($this->branchRelations(true));
        });

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => $this->transformBranch($branch, true),
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $branch->load($this->branchRelations(true));

        return response()->json([
            'data' => $this->transformBranch($branch, true),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $validated = $request->validated();

        $branch = DB::transaction(function () use ($branch, $validated): Branch {
            $branch->update(collect($validated)->except('admin')->all());
            $this->syncLocalAdmin($branch, $validated['admin'] ?? null);

            return $branch->fresh($this->branchRelations(true));
        });

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => $this->transformBranch($branch, true),
        ]);
    }

    public function reassign(ReassignBranchRequest $request, Branch $branch): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['to_parent_branch_id'])) {
            $this->ensureValidBranchParent($branch, (int) $validated['to_parent_branch_id']);
        }

        $branch = DB::transaction(function () use ($validated, $branch): Branch {
            $fromChurchId = $branch->current_parent_church_id;
            $fromBranchId = $branch->current_parent_branch_id;

            $branch->update([
                'current_parent_church_id' => $validated['to_parent_church_id'] ?? null,
                'current_parent_branch_id' => $validated['to_parent_branch_id'] ?? null,
                'last_assigned_by_church_id' => $validated['changed_by_church_id'],
                'last_assigned_by_user_id' => $validated['changed_by_user_id'] ?? null,
                'last_assigned_actor_type' => $validated['changed_by_actor_type'],
            ]);

            $this->recordAssignmentHistory(
                branch: $branch,
                actionType: 'reassign',
                fromChurchId: $fromChurchId,
                fromBranchId: $fromBranchId,
                toChurchId: $validated['to_parent_church_id'] ?? null,
                toBranchId: $validated['to_parent_branch_id'] ?? null,
                changedByChurchId: $validated['changed_by_church_id'],
                changedByUserId: $validated['changed_by_user_id'] ?? null,
                changedByActorType: $validated['changed_by_actor_type'],
                note: $validated['note'] ?? null,
            );

            return $branch->fresh($this->branchRelations(true));
        });

        return response()->json([
            'message' => 'Branch reassigned successfully.',
            'data' => $this->transformBranch($branch, true),
        ]);
    }

    public function detach(Request $request, Branch $branch): JsonResponse
    {
        $changedByChurchId = $request->integer('changed_by_church_id');
        $changedByUserId = $request->integer('changed_by_user_id') ?: null;
        $changedByActorType = $request->string('changed_by_actor_type')->toString() ?: 'user';
        $note = $request->string('note')->toString() ?: null;

        if (!$changedByChurchId) {
            return response()->json([
                'message' => 'changed_by_church_id is required.',
            ], 422);
        }

        $branch = DB::transaction(function () use ($branch, $changedByChurchId, $changedByUserId, $changedByActorType, $note): Branch {
            $fromChurchId = $branch->current_parent_church_id;
            $fromBranchId = $branch->current_parent_branch_id;

            $branch->update([
                'current_parent_church_id' => $branch->created_by_church_id,
                'current_parent_branch_id' => null,
                'last_assigned_by_church_id' => $changedByChurchId,
                'last_assigned_by_user_id' => $changedByUserId,
                'last_assigned_actor_type' => $changedByActorType,
            ]);

            $this->recordAssignmentHistory(
                branch: $branch,
                actionType: 'detach',
                fromChurchId: $fromChurchId,
                fromBranchId: $fromBranchId,
                toChurchId: $branch->created_by_church_id,
                toBranchId: null,
                changedByChurchId: $changedByChurchId,
                changedByUserId: $changedByUserId,
                changedByActorType: $changedByActorType,
                note: $note ?: 'Branch detached back to creator church.',
            );

            return $branch->fresh($this->branchRelations(true));
        });

        return response()->json([
            'message' => 'Branch detached successfully.',
            'data' => $this->transformBranch($branch, true),
        ]);
    }

    public function parentOptions(Request $request): JsonResponse
    {
        $excludeBranchId = $request->integer('exclude_branch_id');
        $excludedIds = $excludeBranchId ? $this->collectDescendantIds($excludeBranchId) : [];

        if ($excludeBranchId) {
            $excludedIds[] = $excludeBranchId;
        }

        $churches = Church::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $branches = Branch::query()
            ->when($excludedIds !== [], fn (Builder $builder) => $builder->whereNotIn('id', $excludedIds))
            ->with(['tag:id,name,slug'])
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch): array {
                return [
                    'id' => $branch->id,
                    'type' => 'branch',
                    'name' => $branch->name,
                    'tag_name' => $branch->tag?->name,
                ];
            });

        return response()->json([
            'data' => [
                'churches' => $churches->map(fn (Church $church) => [
                    'id' => $church->id,
                    'type' => 'church',
                    'name' => $church->name,
                    'code' => $church->code,
                ]),
                'branches' => $branches,
            ],
        ]);
    }

    private function branchRelations(bool $withHistory): array
    {
        $relations = [
            'tag:id,name,slug',
            'creatorChurch:id,name,code',
            'creatorUser:id,name,email',
            'currentParentChurch:id,name,code',
            'currentParentBranch:id,name',
            'lastAssignedByChurch:id,name,code',
            'lastAssignedByUser:id,name,email',
            'localAdmin:id,church_id,branch_id,name,email,phone,role',
        ];

        if ($withHistory) {
            $relations['assignmentHistories'] = function ($query) {
                $query->with([
                    'fromParentChurch:id,name,code',
                    'fromParentBranch:id,name',
                    'toParentChurch:id,name,code',
                    'toParentBranch:id,name',
                    'changedByChurch:id,name,code',
                    'changedByUser:id,name,email',
                ]);
            };
        }

        return $relations;
    }

    private function transformBranch(Branch $branch, bool $includeHistory): array
    {
        $payload = [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'status' => $branch->status,
            'address' => $branch->address,
            'city' => $branch->city,
            'state' => $branch->state,
            'district_area' => $branch->district_area,
            'email' => $branch->email,
            'phone' => $branch->phone,
            'pastor_name' => $branch->pastor_name,
            'pastor_phone' => $branch->pastor_phone,
            'pastor_email' => $branch->pastor_email,
            'local_admin' => ($localAdmin = $branch->localAdmin->first()) ? [
                'id' => $localAdmin->id,
                'name' => $localAdmin->name,
                'email' => $localAdmin->email,
                'phone' => $localAdmin->phone,
                'role' => $localAdmin->role,
                'church_id' => $localAdmin->church_id,
                'branch_id' => $localAdmin->branch_id,
            ] : null,
            'tag' => $branch->tag ? [
                'id' => $branch->tag->id,
                'name' => $branch->tag->name,
                'slug' => $branch->tag->slug,
            ] : null,
            'creator_church' => $branch->creatorChurch ? [
                'id' => $branch->creatorChurch->id,
                'name' => $branch->creatorChurch->name,
                'code' => $branch->creatorChurch->code,
            ] : null,
            'creator_user' => $branch->creatorUser ? [
                'id' => $branch->creatorUser->id,
                'name' => $branch->creatorUser->name,
                'email' => $branch->creatorUser->email,
            ] : null,
            'created_by_actor_type' => $branch->created_by_actor_type,
            'current_parent' => $branch->currentParentBranch
                ? [
                    'type' => 'branch',
                    'id' => $branch->currentParentBranch->id,
                    'name' => $branch->currentParentBranch->name,
                ]
                : ($branch->currentParentChurch ? [
                    'type' => 'church',
                    'id' => $branch->currentParentChurch->id,
                    'name' => $branch->currentParentChurch->name,
                    'code' => $branch->currentParentChurch->code,
                ] : null),
            'last_assignment' => [
                'actor_type' => $branch->last_assigned_actor_type,
                'church' => $branch->lastAssignedByChurch ? [
                    'id' => $branch->lastAssignedByChurch->id,
                    'name' => $branch->lastAssignedByChurch->name,
                    'code' => $branch->lastAssignedByChurch->code,
                ] : null,
                'user' => $branch->lastAssignedByUser ? [
                    'id' => $branch->lastAssignedByUser->id,
                    'name' => $branch->lastAssignedByUser->name,
                    'email' => $branch->lastAssignedByUser->email,
                ] : null,
            ],
            'created_at' => $branch->created_at,
            'updated_at' => $branch->updated_at,
        ];

        if ($includeHistory) {
            $payload['assignment_history'] = $branch->assignmentHistories->map(function (BranchAssignmentHistory $history) {
                return [
                    'id' => $history->id,
                    'action_type' => $history->action_type,
                    'from_parent' => $history->fromParentBranch
                        ? ['type' => 'branch', 'id' => $history->fromParentBranch->id, 'name' => $history->fromParentBranch->name]
                        : ($history->fromParentChurch ? ['type' => 'church', 'id' => $history->fromParentChurch->id, 'name' => $history->fromParentChurch->name, 'code' => $history->fromParentChurch->code] : null),
                    'to_parent' => $history->toParentBranch
                        ? ['type' => 'branch', 'id' => $history->toParentBranch->id, 'name' => $history->toParentBranch->name]
                        : ($history->toParentChurch ? ['type' => 'church', 'id' => $history->toParentChurch->id, 'name' => $history->toParentChurch->name, 'code' => $history->toParentChurch->code] : null),
                    'changed_by_actor_type' => $history->changed_by_actor_type,
                    'changed_by_church' => $history->changedByChurch ? [
                        'id' => $history->changedByChurch->id,
                        'name' => $history->changedByChurch->name,
                        'code' => $history->changedByChurch->code,
                    ] : null,
                    'changed_by_user' => $history->changedByUser ? [
                        'id' => $history->changedByUser->id,
                        'name' => $history->changedByUser->name,
                        'email' => $history->changedByUser->email,
                    ] : null,
                    'note' => $history->note,
                    'created_at' => $history->created_at,
                ];
            })->values();
        }

        return $payload;
    }

    private function generateBranchCode(string $branchName): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $branchName), 0, 8));

        return trim($prefix ?: 'BRANCH').'-'.Str::upper(Str::random(6));
    }

    private function ensureValidBranchParent(Branch $branch, int $parentBranchId): void
    {
        if ($branch->id === $parentBranchId) {
            abort(response()->json([
                'message' => 'A branch cannot be assigned under itself.',
            ], 422));
        }

        if (in_array($parentBranchId, $this->collectDescendantIds($branch->id), true)) {
            abort(response()->json([
                'message' => 'A branch cannot be assigned under one of its descendants.',
            ], 422));
        }
    }

    private function collectDescendantIds(int $branchId): array
    {
        $allBranches = Branch::query()
            ->get(['id', 'current_parent_branch_id']);

        $descendantIds = [];
        $queue = [$branchId];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            foreach ($allBranches as $candidate) {
                if ($candidate->current_parent_branch_id !== $currentId) {
                    continue;
                }

                $descendantIds[] = $candidate->id;
                $queue[] = $candidate->id;
            }
        }

        return array_values(array_unique($descendantIds));
    }

    private function recordAssignmentHistory(
        Branch $branch,
        string $actionType,
        ?int $fromChurchId,
        ?int $fromBranchId,
        ?int $toChurchId,
        ?int $toBranchId,
        ?int $changedByChurchId,
        ?int $changedByUserId,
        ?string $changedByActorType,
        ?string $note,
    ): void {
        BranchAssignmentHistory::create([
            'branch_id' => $branch->id,
            'action_type' => $actionType,
            'from_parent_church_id' => $fromChurchId,
            'from_parent_branch_id' => $fromBranchId,
            'to_parent_church_id' => $toChurchId,
            'to_parent_branch_id' => $toBranchId,
            'changed_by_church_id' => $changedByChurchId,
            'changed_by_user_id' => $changedByUserId,
            'changed_by_actor_type' => $changedByActorType,
            'note' => $note,
        ]);
    }

    private function syncLocalAdmin(Branch $branch, ?array $adminData): void
    {
        if (!is_array($adminData)) {
            return;
        }

        $hasAdminValues = collect([
            data_get($adminData, 'name'),
            data_get($adminData, 'email'),
            data_get($adminData, 'phone'),
            data_get($adminData, 'password'),
        ])->contains(fn ($value) => filled($value));

        if (!$hasAdminValues) {
            return;
        }

        $existingAdmin = $branch->localAdmin()->first();

        $attributes = [
            'church_id' => $branch->created_by_church_id,
            'branch_id' => $branch->id,
            'name' => data_get($adminData, 'name') ?: $existingAdmin?->name,
            'email' => data_get($adminData, 'email') ?: $existingAdmin?->email,
            'phone' => data_get($adminData, 'phone') ?: $existingAdmin?->phone,
            'role' => 'branch_admin',
        ];

        if (filled(data_get($adminData, 'password'))) {
            $attributes['password'] = Hash::make($adminData['password']);
        }

        if ($existingAdmin) {
            $existingAdmin->update($attributes);
            return;
        }

        User::create($attributes);
    }
}
