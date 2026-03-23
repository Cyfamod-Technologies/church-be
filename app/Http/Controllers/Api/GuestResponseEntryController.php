<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGuestResponseEntryRequest;
use App\Http\Requests\Api\UpdateGuestResponseEntryRequest;
use App\Models\GuestResponseEntry;
use App\Support\BranchHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestResponseEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'church_id' => ['required', 'integer', 'exists:churches,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'entry_type' => ['nullable', 'string', 'in:first_timer,new_convert,rededication'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $branchScopeIds = $request->integer('branch_id')
            ? BranchHierarchy::descendantIdsInclusive($request->integer('branch_id'))
            : [];

        $records = GuestResponseEntry::query()
            ->with(['branch:id,name,code', 'recordedBy:id,name,email', 'churchUnits:id,name,code,status'])
            ->where('church_id', $request->integer('church_id'))
            ->when($branchScopeIds !== [], fn (Builder $query) => $query->whereIn('branch_id', $branchScopeIds))
            ->when($request->filled('entry_type'), fn (Builder $query) => $query->where('entry_type', $request->string('entry_type')->toString()))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('service_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('service_date', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery
                        ->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('invited_by', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->limit($request->integer('limit', 50))
            ->get();

        return response()->json([
            'data' => $records->map(fn (GuestResponseEntry $entry) => $this->transformEntry($entry)),
        ]);
    }

    public function store(StoreGuestResponseEntryRequest $request): JsonResponse
    {
        $entry = $this->persistEntry(new GuestResponseEntry(), $request->validated());

        return response()->json([
            'message' => 'Entry saved successfully.',
            'data' => $this->transformEntry($entry),
        ], 201);
    }

    public function show(GuestResponseEntry $guestResponseEntry): JsonResponse
    {
        $guestResponseEntry->load(['branch:id,name,code', 'recordedBy:id,name,email', 'churchUnits:id,name,code,status']);

        return response()->json([
            'data' => $this->transformEntry($guestResponseEntry),
        ]);
    }

    public function update(UpdateGuestResponseEntryRequest $request, GuestResponseEntry $guestResponseEntry): JsonResponse
    {
        $entry = $this->persistEntry($guestResponseEntry, $request->validated());

        return response()->json([
            'message' => 'Entry updated successfully.',
            'data' => $this->transformEntry($entry),
        ]);
    }

    private function persistEntry(GuestResponseEntry $entry, array $validated): GuestResponseEntry
    {
        $wofbiLevels = $this->normalizeWofbiLevels($validated);
        return DB::transaction(function () use ($entry, $validated, $wofbiLevels): GuestResponseEntry {
            $entry->fill([
                'church_id' => $validated['church_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'recorded_by_user_id' => $validated['recorded_by_user_id'] ?? null,
                'entry_type' => $validated['entry_type'],
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'service_date' => $validated['service_date'],
                'invited_by' => $validated['invited_by'] ?? null,
                'address' => $validated['address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'foundation_class_completed' => (bool) ($validated['foundation_class_completed'] ?? false),
                'baptism_completed' => (bool) ($validated['baptism_completed'] ?? false),
                'holy_ghost_baptism_completed' => (bool) ($validated['holy_ghost_baptism_completed'] ?? false),
                'wofbi_completed' => (bool) ($validated['wofbi_completed'] ?? false),
                'wofbi_level' => ! empty($validated['wofbi_completed']) && ! empty($wofbiLevels) ? implode(', ', $wofbiLevels) : null,
                'wofbi_levels' => ! empty($validated['wofbi_completed']) ? $wofbiLevels : null,
            ]);

            $entry->save();
            $entry->churchUnits()->sync($validated['church_unit_ids'] ?? []);

            return $entry->load(['branch:id,name,code', 'recordedBy:id,name,email', 'churchUnits:id,name,code,status']);
        });
    }

    private function transformEntry(GuestResponseEntry $entry): array
    {
        $wofbiLevels = $entry->wofbi_levels;

        if (! is_array($wofbiLevels) || empty($wofbiLevels)) {
            $wofbiLevels = $entry->wofbi_level
                ? array_values(array_filter(array_map('trim', explode(',', $entry->wofbi_level))))
                : [];
        }

        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'full_name' => $entry->full_name,
            'phone' => $entry->phone,
            'email' => $entry->email,
            'gender' => $entry->gender,
            'service_date' => $entry->service_date?->toDateString(),
            'invited_by' => $entry->invited_by,
            'address' => $entry->address,
            'notes' => $entry->notes,
            'foundation_class_completed' => (bool) $entry->foundation_class_completed,
            'baptism_completed' => (bool) $entry->baptism_completed,
            'holy_ghost_baptism_completed' => (bool) $entry->holy_ghost_baptism_completed,
            'wofbi_completed' => (bool) $entry->wofbi_completed,
            'wofbi_level' => $entry->wofbi_level,
            'wofbi_levels' => $wofbiLevels,
            'church_units' => $entry->churchUnits->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'status' => $unit->status,
            ])->values(),
            'branch' => $entry->branch ? [
                'id' => $entry->branch->id,
                'name' => $entry->branch->name,
                'code' => $entry->branch->code,
            ] : null,
            'recorded_by' => $entry->recordedBy ? [
                'id' => $entry->recordedBy->id,
                'name' => $entry->recordedBy->name,
                'email' => $entry->recordedBy->email,
            ] : null,
            'created_at' => $entry->created_at,
        ];
    }

    private function normalizeWofbiLevels(array $validated): array
    {
        $levels = $validated['wofbi_levels'] ?? [];

        if (! is_array($levels) || empty($levels)) {
            $levels = ! empty($validated['wofbi_level']) ? [$validated['wofbi_level']] : [];
        }

        $levels = array_values(array_unique(array_filter(array_map('strval', $levels))));

        return array_values(array_filter($levels, fn (string $level) => in_array($level, ['BCC', 'LCC', 'LDC'], true)));
    }
}
