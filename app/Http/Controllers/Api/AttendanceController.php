<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = AttendanceRecord::query()
            ->with(['church:id,name,code', 'serviceSchedule:id,label,service_type', 'recordedBy:id,name,email'])
            ->when($request->integer('church_id'), fn (Builder $query, int $churchId) => $query->where('church_id', $churchId))
            ->when($request->filled('service_type'), fn (Builder $query) => $query->where('service_type', $request->string('service_type')->toString()))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('service_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('service_date', '<=', $request->date('date_to')))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($records);
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $total = (int) $validated['male_count'] + (int) $validated['female_count'] + (int) $validated['children_count'];
        $serviceLabel = $validated['service_label'] ?? $this->buildServiceLabel($validated);

        $record = AttendanceRecord::create([
            'church_id' => $validated['church_id'],
            'service_schedule_id' => $validated['service_schedule_id'] ?? null,
            'recorded_by_user_id' => $validated['recorded_by_user_id'] ?? null,
            'service_date' => $validated['service_date'],
            'service_type' => $validated['service_type'],
            'service_label' => $serviceLabel,
            'sunday_service_number' => $validated['sunday_service_number'] ?? null,
            'special_service_name' => $validated['special_service_name'] ?? null,
            'male_count' => $validated['male_count'],
            'female_count' => $validated['female_count'],
            'children_count' => $validated['children_count'],
            'total_count' => $total,
            'first_timers_count' => $validated['first_timers_count'] ?? 0,
            'new_converts_count' => $validated['new_converts_count'] ?? 0,
            'rededications_count' => $validated['rededications_count'] ?? 0,
            'main_offering' => $validated['main_offering'] ?? null,
            'tithe' => $validated['tithe'] ?? null,
            'special_offering' => $validated['special_offering'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->load(['church:id,name,code', 'serviceSchedule:id,label,service_type']);

        return response()->json([
            'message' => 'Attendance saved successfully.',
            'data' => $record,
        ], 201);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'church_id' => ['required', 'integer', 'exists:churches,id'],
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

        $records = AttendanceRecord::query()
            ->where('church_id', $request->integer('church_id'))
            ->whereBetween('service_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('service_date')
            ->get();

        $totalAttendance = (int) $records->sum('total_count');
        $averageAttendance = $records->count() > 0 ? round($totalAttendance / $records->count(), 2) : 0;
        $highest = $records->sortByDesc('total_count')->first();

        return response()->json([
            'data' => [
                'period' => $period,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'total_attendance' => $totalAttendance,
                'average_attendance' => $averageAttendance,
                'highest_service' => $highest ? [
                    'service_label' => $highest->service_label,
                    'service_date' => $highest->service_date->toDateString(),
                    'total_count' => $highest->total_count,
                ] : null,
                'breakdown' => $records
                    ->groupBy('service_type')
                    ->map(fn ($group) => [
                        'total' => (int) $group->sum('total_count'),
                        'count' => $group->count(),
                        'average' => $group->count() > 0 ? round($group->sum('total_count') / $group->count(), 2) : 0,
                    ]),
            ],
        ]);
    }

    private function buildServiceLabel(array $validated): string
    {
        return match ($validated['service_type']) {
            'sunday' => ($validated['sunday_service_number'] ?? 1).$this->ordinalSuffix((int) ($validated['sunday_service_number'] ?? 1)).' Service',
            'wednesday' => 'Wednesday Service',
            'wose' => $validated['special_service_name'] ?? 'WOSE Service',
            'special' => $validated['special_service_name'] ?? 'Special Service',
            default => 'Service',
        };
    }

    private function ordinalSuffix(int $value): string
    {
        return match ($value) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
