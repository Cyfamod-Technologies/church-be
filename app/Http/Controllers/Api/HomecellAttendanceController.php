<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHomecellAttendanceRequest;
use App\Http\Requests\Api\UpdateHomecellAttendanceRequest;
use App\Models\Homecell;
use App\Models\HomecellAttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomecellAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'church_id' => ['required', 'integer', 'exists:churches,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'homecell_id' => ['nullable', 'integer', 'exists:homecells,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $records = HomecellAttendanceRecord::query()
            ->with([
                'branch:id,name,code',
                'homecell:id,name,code,branch_id',
                'recordedBy:id,name,email',
            ])
            ->where('church_id', $request->integer('church_id'))
            ->when($request->integer('branch_id'), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($request->integer('homecell_id'), fn (Builder $query, int $homecellId) => $query->where('homecell_id', $homecellId))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('meeting_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('meeting_date', '<=', $request->date('date_to')))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->limit($request->integer('limit', 25))
            ->get();

        return response()->json([
            'data' => $records->map(fn (HomecellAttendanceRecord $record) => $this->transformRecord($record)),
        ]);
    }

    public function store(StoreHomecellAttendanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $homecell = Homecell::query()->findOrFail($validated['homecell_id']);
        $record = $this->persistRecord(new HomecellAttendanceRecord(), $validated, $homecell);

        return response()->json([
            'message' => 'Homecell attendance saved successfully.',
            'data' => $this->transformRecord($record),
        ], 201);
    }

    public function show(HomecellAttendanceRecord $homecellAttendanceRecord): JsonResponse
    {
        return response()->json([
            'data' => $this->transformRecord($homecellAttendanceRecord->load([
                'branch:id,name,code',
                'homecell:id,name,code,branch_id',
                'recordedBy:id,name,email',
            ])),
        ]);
    }

    public function update(UpdateHomecellAttendanceRequest $request, HomecellAttendanceRecord $homecellAttendanceRecord): JsonResponse
    {
        $validated = $request->validated();
        $homecell = Homecell::query()->findOrFail($validated['homecell_id']);
        $record = $this->persistRecord($homecellAttendanceRecord, $validated, $homecell);

        return response()->json([
            'message' => 'Homecell attendance updated successfully.',
            'data' => $this->transformRecord($record),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'church_id' => ['required', 'integer', 'exists:churches,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'homecell_id' => ['nullable', 'integer', 'exists:homecells,id'],
            'period' => ['nullable', 'in:weekly,monthly'],
            'date' => ['nullable', 'date'],
        ]);

        $anchorDate = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())->startOfDay()
            : now()->startOfDay();

        $period = $request->string('period', 'weekly')->toString();
        [$dateFrom, $dateTo] = $period === 'monthly'
            ? [$anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()]
            : [$anchorDate->copy()->startOfWeek(), $anchorDate->copy()->endOfWeek()];

        $records = HomecellAttendanceRecord::query()
            ->where('church_id', $request->integer('church_id'))
            ->when($request->integer('branch_id'), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($request->integer('homecell_id'), fn (Builder $query, int $homecellId) => $query->where('homecell_id', $homecellId))
            ->whereBetween('meeting_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get();

        $activeHomecells = Homecell::query()
            ->where('church_id', $request->integer('church_id'))
            ->where('status', 'active')
            ->when($request->integer('branch_id'), fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($request->integer('homecell_id'), fn (Builder $query, int $homecellId) => $query->whereKey($homecellId))
            ->count();

        $totalAttendance = (int) $records->sum('total_count');
        $reportsSubmitted = $records->count();
        $homecellsCovered = $records->pluck('homecell_id')->unique()->count();
        $averageAttendance = $reportsSubmitted > 0 ? round($totalAttendance / $reportsSubmitted, 2) : 0;
        $highest = $records->sortByDesc('total_count')->first();

        return response()->json([
            'data' => [
                'period' => $period,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'total_attendance' => $totalAttendance,
                'reports_submitted' => $reportsSubmitted,
                'average_attendance' => $averageAttendance,
                'active_homecells' => $activeHomecells,
                'homecells_covered' => $homecellsCovered,
                'pending_homecells' => max($activeHomecells - $homecellsCovered, 0),
                'highest_attendance' => $highest ? [
                    'meeting_date' => $highest->meeting_date->toDateString(),
                    'homecell_id' => $highest->homecell_id,
                    'total_count' => $highest->total_count,
                ] : null,
            ],
        ]);
    }

    private function transformRecord(HomecellAttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'meeting_date' => $record->meeting_date?->toDateString(),
            'male_count' => $record->male_count,
            'female_count' => $record->female_count,
            'children_count' => $record->children_count,
            'total_count' => $record->total_count,
            'first_timers_count' => $record->first_timers_count,
            'new_converts_count' => $record->new_converts_count,
            'offering_amount' => $record->offering_amount,
            'notes' => $record->notes,
            'branch' => $record->branch ? [
                'id' => $record->branch->id,
                'name' => $record->branch->name,
                'code' => $record->branch->code,
            ] : null,
            'homecell' => $record->homecell ? [
                'id' => $record->homecell->id,
                'name' => $record->homecell->name,
                'code' => $record->homecell->code,
            ] : null,
            'recorded_by' => $record->recordedBy ? [
                'id' => $record->recordedBy->id,
                'name' => $record->recordedBy->name,
                'email' => $record->recordedBy->email,
            ] : null,
            'created_at' => $record->created_at,
        ];
    }

    private function persistRecord(HomecellAttendanceRecord $record, array $validated, Homecell $homecell): HomecellAttendanceRecord
    {
        $total = (int) $validated['male_count'] + (int) $validated['female_count'] + (int) $validated['children_count'];

        $record->fill([
            'church_id' => $validated['church_id'],
            'branch_id' => $validated['branch_id'] ?? $homecell->branch_id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $validated['recorded_by_user_id'] ?? null,
            'meeting_date' => $validated['meeting_date'],
            'male_count' => $validated['male_count'],
            'female_count' => $validated['female_count'],
            'children_count' => $validated['children_count'],
            'total_count' => $total,
            'first_timers_count' => $validated['first_timers_count'] ?? 0,
            'new_converts_count' => $validated['new_converts_count'] ?? 0,
            'offering_amount' => $validated['offering_amount'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $record->save();

        return $record->load([
            'branch:id,name,code',
            'homecell:id,name,code,branch_id',
            'recordedBy:id,name,email',
        ]);
    }
}
