<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'services.sunday_count' => ['required', 'integer', 'min:1', 'max:4'],
            'services.sunday_times' => ['required', 'array', 'min:1', 'max:4'],
            'services.sunday_times.*' => ['required', 'date_format:H:i'],
            'services.wednesday_enabled' => ['required', 'boolean'],
            'services.wednesday_time' => ['nullable', 'date_format:H:i'],
            'services.wose_enabled' => ['required', 'boolean'],
            'services.wose_times' => ['nullable', 'array'],
            'services.wose_times.wednesday' => ['nullable', 'date_format:H:i'],
            'services.wose_times.thursday' => ['nullable', 'date_format:H:i'],
            'services.wose_times.friday' => ['nullable', 'date_format:H:i'],
            'services.custom_services' => ['nullable', 'array'],
            'services.custom_services.*.label' => ['required', 'string', 'max:255'],
            'services.custom_services.*.day_name' => ['nullable', 'string', 'max:50'],
            'services.custom_services.*.service_time' => ['required', 'date_format:H:i'],
            'services.custom_services.*.recurrence_type' => ['required', 'string', 'in:weekly,monthly,yearly,one_time'],
            'services.custom_services.*.recurrence_detail' => ['nullable', 'string', 'max:255'],
            'services.special_services_enabled' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $services = $this->input('services', []);
                $sundayCount = (int) ($services['sunday_count'] ?? 0);
                $sundayTimes = $services['sunday_times'] ?? [];

                if ($sundayCount > count($sundayTimes)) {
                    $validator->errors()->add('services.sunday_times', 'Provide a time for each configured Sunday service.');
                }

                if (($services['wednesday_enabled'] ?? false) && empty($services['wednesday_time'])) {
                    $validator->errors()->add('services.wednesday_time', 'Wednesday service time is required when Wednesday service is enabled.');
                }

                if ($services['wose_enabled'] ?? false) {
                    foreach (['wednesday', 'thursday', 'friday'] as $day) {
                        if (empty($services['wose_times'][$day] ?? null)) {
                            $validator->errors()->add("services.wose_times.{$day}", 'All WOSE service times are required when WOSE is enabled.');
                        }
                    }
                }

                foreach (($services['custom_services'] ?? []) as $index => $service) {
                    $recurrenceType = $service['recurrence_type'] ?? null;
                    $recurrenceDetail = trim((string) ($service['recurrence_detail'] ?? ''));

                    if (in_array($recurrenceType, ['monthly', 'yearly', 'one_time'], true) && $recurrenceDetail === '') {
                        $validator->errors()->add(
                            "services.custom_services.{$index}.recurrence_detail",
                            'Add a recurrence detail for monthly, yearly, or one-time services.'
                        );
                    }
                }
            },
        ];
    }
}
