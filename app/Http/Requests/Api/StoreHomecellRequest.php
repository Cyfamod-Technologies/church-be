<?php

namespace App\Http\Requests\Api;

use App\Models\Branch;
use App\Models\Church;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHomecellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists(Church::class, 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('homecells', 'code')],
            'meeting_day' => ['nullable', 'string', 'max:50'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'host_phone' => ['nullable', 'string', 'max:30'],
            'city_area' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:50'],
            'leaders' => ['nullable', 'array'],
            'leaders.*.name' => ['required', 'string', 'max:255'],
            'leaders.*.role' => ['nullable', 'string', 'max:100'],
            'leaders.*.phone' => ['nullable', 'string', 'max:30'],
            'leaders.*.email' => ['nullable', 'email', 'max:255'],
            'leaders.*.is_primary' => ['nullable', 'boolean'],
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
        });
    }
}
