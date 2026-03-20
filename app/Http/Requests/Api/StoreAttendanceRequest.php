<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
}
