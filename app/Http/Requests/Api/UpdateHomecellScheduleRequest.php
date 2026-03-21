<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomecellScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'homecell_schedule.locked' => ['required', 'boolean'],
            'homecell_schedule.default_day' => ['nullable', 'string', 'max:50'],
            'homecell_schedule.default_time' => ['nullable', 'date_format:H:i'],
            'homecell_schedule.monthly_dates' => ['nullable', 'array'],
            'homecell_schedule.monthly_dates.*' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $schedule = $this->input('homecell_schedule', []);

                if (($schedule['locked'] ?? false) && empty($schedule['default_day'])) {
                    $validator->errors()->add('homecell_schedule.default_day', 'Default meeting day is required when the schedule is locked.');
                }

                if (($schedule['locked'] ?? false) && empty($schedule['default_time'])) {
                    $validator->errors()->add('homecell_schedule.default_time', 'Default meeting time is required when the schedule is locked.');
                }
            },
        ];
    }
}
