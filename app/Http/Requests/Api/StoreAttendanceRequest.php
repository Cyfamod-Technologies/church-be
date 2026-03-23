<?php

namespace App\Http\Requests\Api;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', 'exists:churches,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'service_schedule_id' => ['nullable', 'integer', 'exists:service_schedules,id'],
            'recorded_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_date' => ['required', 'date'],
            'service_type' => ['required', 'string', 'in:sunday,wednesday,wose,special'],
            'service_label' => ['nullable', 'string', 'max:255'],
            'sunday_service_number' => ['nullable', 'integer', 'min:1', 'max:4'],
            'special_service_name' => ['nullable', 'string', 'max:255'],
            'male_count' => ['required', 'integer', 'min:0'],
            'female_count' => ['required', 'integer', 'min:0'],
            'children_count' => ['required', 'integer', 'min:0'],
            'first_timers_count' => ['nullable', 'integer', 'min:0'],
            'new_converts_count' => ['nullable', 'integer', 'min:0'],
            'rededications_count' => ['nullable', 'integer', 'min:0'],
            'main_offering' => ['nullable', 'numeric', 'min:0'],
            'tithe' => ['nullable', 'numeric', 'min:0'],
            'special_offering' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $churchId = $this->integer('church_id');
            $branchId = $this->integer('branch_id');

            if ($branchId && $churchId) {
                $branchBelongsToChurch = Branch::query()
                    ->whereKey($branchId)
                    ->where('created_by_church_id', $churchId)
                    ->exists();

                if (! $branchBelongsToChurch) {
                    $validator->errors()->add('branch_id', 'Select a branch that belongs to this church.');
                }
            }

            if (! $churchId || ! $this->filled('service_date') || ! $this->filled('service_type')) {
                return;
            }

            $attendanceRecord = $this->route('attendanceRecord');
            $currentRecordId = $attendanceRecord instanceof AttendanceRecord ? $attendanceRecord->id : (is_numeric($attendanceRecord) ? (int) $attendanceRecord : null);

            $duplicateQuery = AttendanceRecord::query()
                ->where('church_id', $churchId)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId), fn ($query) => $query->whereNull('branch_id'))
                ->whereDate('service_date', $this->date('service_date'))
                ->when($currentRecordId, fn ($query, int $recordId) => $query->whereKeyNot($recordId));

            $serviceScheduleId = $this->integer('service_schedule_id');
            $serviceType = $this->string('service_type')->toString();
            $sundayServiceNumber = $this->integer('sunday_service_number');
            $specialServiceName = trim((string) $this->input('special_service_name', ''));
            $serviceLabel = trim((string) $this->input('service_label', ''));

            if ($serviceScheduleId) {
                $duplicateQuery->where('service_schedule_id', $serviceScheduleId);
            } elseif ($serviceType === 'sunday' && $sundayServiceNumber > 0) {
                $duplicateQuery
                    ->where('service_type', 'sunday')
                    ->where('sunday_service_number', $sundayServiceNumber);
            } else {
                $duplicateQuery->where('service_type', $serviceType);

                if ($specialServiceName !== '') {
                    $duplicateQuery->whereRaw('LOWER(COALESCE(special_service_name, service_label)) = ?', [mb_strtolower($specialServiceName)]);
                } elseif ($serviceLabel !== '') {
                    $duplicateQuery->whereRaw('LOWER(service_label) = ?', [mb_strtolower($serviceLabel)]);
                }
            }

            if ($duplicateQuery->exists()) {
                $validator->errors()->add('service_date', 'Attendance has already been recorded for this service on the selected date. Open the existing record and edit it instead.');
            }
        });
    }
}
