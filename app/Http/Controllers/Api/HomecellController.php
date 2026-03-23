<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHomecellRequest;
use App\Http\Requests\Api\UpdateHomecellRequest;
use App\Models\Branch;
use App\Models\Church;
use App\Models\Homecell;
use App\Models\HomecellLeader;
use App\Models\User;
use App\Support\BranchHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class HomecellController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $churchId = $request->integer('church_id');
        $branchId = $request->integer('branch_id');
        $search = $request->string('search')->toString();
        $branchScopeIds = $branchId ? BranchHierarchy::descendantIdsInclusive($branchId) : [];

        $query = Homecell::query()
            ->with($this->relations())
            ->when($churchId, fn (Builder $builder) => $builder->where('church_id', $churchId))
            ->when($branchScopeIds !== [], fn (Builder $builder) => $builder->whereIn('branch_id', $branchScopeIds))
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
            $church = Church::query()->findOrFail($validated['church_id']);
            $meetingDay = $church->homecell_schedule_locked
                ? ($church->homecell_default_day ?: ($validated['meeting_day'] ?? null))
                : ($validated['meeting_day'] ?? null);
            $meetingTime = $church->homecell_schedule_locked
                ? ($church->homecell_default_time ?: ($validated['meeting_time'] ?? null))
                : ($validated['meeting_time'] ?? null);

            $homecell = Homecell::create([
                'church_id' => $validated['church_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'name' => $validated['name'],
                'code' => ($validated['code'] ?? null) ?: $this->generateCode($validated['name']),
                'meeting_day' => $meetingDay,
                'meeting_time' => $meetingTime,
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
            $church = $homecell->church()->first();
            $meetingDay = $church && $church->homecell_schedule_locked
                ? ($church->homecell_default_day ?: ($validated['meeting_day'] ?? null))
                : ($validated['meeting_day'] ?? null);
            $meetingTime = $church && $church->homecell_schedule_locked
                ? ($church->homecell_default_time ?: ($validated['meeting_time'] ?? null))
                : ($validated['meeting_time'] ?? null);

            $homecell->update([
                'branch_id' => $validated['branch_id'] ?? null,
                'name' => $validated['name'],
                'code' => ($validated['code'] ?? null) ?: $homecell->code ?: $this->generateCode($validated['name']),
                'meeting_day' => $meetingDay,
                'meeting_time' => $meetingTime,
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
            'church:id,name,code,homecell_schedule_locked,homecell_default_day,homecell_default_time,homecell_monthly_dates',
            'branch:id,name,code',
            'leaders.user:id,name,email,phone,role',
            'leaders:id,homecell_id,user_id,name,role,phone,email,is_primary,sort_order',
        ];
    }

    private function transformHomecell(Homecell $homecell): array
    {
        $scheduleLocked = (bool) ($homecell->church?->homecell_schedule_locked);
        $meetingDay = $scheduleLocked && $homecell->church?->homecell_default_day
            ? $homecell->church->homecell_default_day
            : $homecell->meeting_day;
        $meetingTime = $scheduleLocked && $homecell->church?->homecell_default_time
            ? $homecell->church->homecell_default_time
            : $homecell->meeting_time;

        return [
            'id' => $homecell->id,
            'name' => $homecell->name,
            'code' => $homecell->code,
            'meeting_day' => $meetingDay,
            'meeting_time' => $meetingTime,
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
            'schedule_config' => $homecell->church ? [
                'locked' => $scheduleLocked,
                'default_day' => $homecell->church->homecell_default_day,
                'default_time' => $homecell->church->homecell_default_time,
                'monthly_dates' => $homecell->church->homecell_monthly_dates ?? [],
                'inherited' => $scheduleLocked,
            ] : null,
            'branch' => $homecell->branch ? [
                'id' => $homecell->branch->id,
                'name' => $homecell->branch->name,
                'code' => $homecell->branch->code,
            ] : null,
            'leaders' => $homecell->leaders->map(fn (HomecellLeader $leader) => [
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
            ])->values(),
            'created_at' => $homecell->created_at,
            'updated_at' => $homecell->updated_at,
        ];
    }

    private function syncLeaders(Homecell $homecell, array $leaders): void
    {
        $existingLeaders = $homecell->leaders()->with('user')->get()->keyBy('id');
        $keptLeaderIds = [];

        foreach (array_values($leaders) as $index => $leader) {
            $leaderId = isset($leader['id']) ? (int) $leader['id'] : null;
            $leaderModel = $leaderId ? $existingLeaders->get($leaderId) : null;

            if (! $leaderModel) {
                $leaderModel = new HomecellLeader([
                    'homecell_id' => $homecell->id,
                ]);
            }

            $leaderModel->fill([
                'homecell_id' => $homecell->id,
                'name' => $leader['name'],
                'role' => $leader['role'] ?? 'Leader',
                'phone' => $leader['phone'] ?? null,
                'email' => $leader['email'] ?? null,
                'is_primary' => (bool) ($leader['is_primary'] ?? false),
                'sort_order' => $index + 1,
            ]);
            $leaderModel->save();

            $this->syncLeaderLoginAccount($homecell, $leaderModel, $leader);
            $keptLeaderIds[] = $leaderModel->id;
        }

        $leadersToDelete = $existingLeaders
            ->filter(fn (HomecellLeader $leader) => ! in_array($leader->id, $keptLeaderIds, true));

        foreach ($leadersToDelete as $leaderToDelete) {
            $leaderToDelete->delete();
        }
    }

    private function syncLeaderLoginAccount(Homecell $homecell, HomecellLeader $leaderModel, array $leader): void
    {
        $shouldCreateOrUpdateLogin = isset($leader['password']) && trim((string) $leader['password']) !== '';
        $linkedUserId = isset($leader['user_id']) ? (int) $leader['user_id'] : null;

        if (! $shouldCreateOrUpdateLogin && ! $leaderModel->user_id && ! $linkedUserId) {
            return;
        }

        $linkedUser = $linkedUserId ? User::query()->find($linkedUserId) : null;

        if ($linkedUserId && ! $linkedUser) {
            throw ValidationException::withMessages([
                'leaders' => ['The selected leader login account could not be found.'],
            ]);
        }

        $user = $leaderModel->user
            ?: $linkedUser
            ?: new User([
            'church_id' => $homecell->church_id,
            'branch_id' => $homecell->branch_id,
            'role' => 'homecell_leader',
        ]);

        if ($user->exists && $user->church_id !== $homecell->church_id) {
            throw ValidationException::withMessages([
                'leaders' => ['A leader login account can only be linked within the same church.'],
            ]);
        }

        $email = $leader['email'] ?? null;
        $phone = $leader['phone'] ?? null;

        $this->guardUniqueUserIdentity($user->id, $email, $phone);

        $user->fill([
            'church_id' => $homecell->church_id,
            'branch_id' => $homecell->branch_id,
            'name' => $leader['name'],
            'email' => $email,
            'phone' => $phone,
            'role' => 'homecell_leader',
        ]);

        if ($shouldCreateOrUpdateLogin) {
            $user->password = $leader['password'];
        }

        $user->save();

        if ($leaderModel->user_id !== $user->id) {
            $leaderModel->user_id = $user->id;
            $leaderModel->save();
        }
    }

    private function guardUniqueUserIdentity(?int $ignoreUserId, ?string $email, ?string $phone): void
    {
        $email = $email !== null ? trim($email) : null;
        $phone = $phone !== null ? trim($phone) : null;

        if ($email !== null && $email !== '') {
            $emailExists = User::query()
                ->where('email', $email)
                ->when($ignoreUserId, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
                ->exists();

            if ($emailExists) {
                throw ValidationException::withMessages([
                    'leaders' => ['That email address is already in use by another user account.'],
                ]);
            }
        }

        if ($phone !== null && $phone !== '') {
            $phoneExists = User::query()
                ->where('phone', $phone)
                ->when($ignoreUserId, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
                ->exists();

            if ($phoneExists) {
                throw ValidationException::withMessages([
                    'leaders' => ['That phone number is already in use by another user account.'],
                ]);
            }
        }
    }

    private function generateCode(string $name): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8));

        return trim($prefix ?: 'HC').'-'.Str::upper(Str::random(6));
    }
}
