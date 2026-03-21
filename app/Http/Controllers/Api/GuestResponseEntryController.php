<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGuestResponseEntryRequest;
use App\Http\Requests\Api\UpdateGuestResponseEntryRequest;
use App\Models\GuestResponseEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $records = GuestResponseEntry::query()
            ->with(['branch:id,name,code', 'recordedBy:id,name,email'])
            ->where('church_id', $request->integer('church_id'))
            ->when($request->integer('branch_id'), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
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
        ]);

        $entry->save();

        return $entry->load(['branch:id,name,code', 'recordedBy:id,name,email']);
    }

    private function transformEntry(GuestResponseEntry $entry): array
    {
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
}
