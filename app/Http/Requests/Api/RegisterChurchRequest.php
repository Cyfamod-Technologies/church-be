<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterChurchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church.name' => ['required', 'string', 'max:255'],
            'church.code' => ['nullable', 'string', 'max:50', Rule::unique('churches', 'code')],
            'church.address' => ['nullable', 'string', 'max:255'],
            'church.city' => ['nullable', 'string', 'max:100'],
            'church.state' => ['nullable', 'string', 'max:100'],
            'church.district_area' => ['nullable', 'string', 'max:100'],
            'church.email' => ['nullable', 'email', 'max:255'],
            'church.phone' => ['nullable', 'string', 'max:30'],

            'pastor.name' => ['required', 'string', 'max:255'],
            'pastor.phone' => ['required', 'string', 'max:30'],
            'pastor.email' => ['nullable', 'email', 'max:255'],

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
            'services.special_services_enabled' => ['required', 'boolean'],

            'settings.finance_enabled' => ['nullable', 'boolean'],

            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin.phone' => ['required', 'string', 'max:30'],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
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
            },
        ];
    }
}
