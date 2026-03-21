<?php

namespace App\Http\Requests\Api;

use App\Models\Branch;
use App\Models\ChurchUnit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGuestResponseEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')],
            'recorded_by_user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'entry_type' => ['required', 'string', Rule::in(['first_timer', 'new_convert', 'rededication'])],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'service_date' => ['required', 'date'],
            'invited_by' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'foundation_class_completed' => ['nullable', 'boolean'],
            'baptism_completed' => ['nullable', 'boolean'],
            'holy_ghost_baptism_completed' => ['nullable', 'boolean'],
            'wofbi_completed' => ['nullable', 'boolean'],
            'wofbi_level' => ['nullable', 'string', Rule::in(['BCC', 'LCC', 'LDC'])],
            'wofbi_levels' => ['nullable', 'array'],
            'wofbi_levels.*' => ['string', Rule::in(['BCC', 'LCC', 'LDC'])],
            'church_unit_ids' => ['nullable', 'array'],
            'church_unit_ids.*' => ['integer', Rule::exists(ChurchUnit::class, 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $record = $this->route('guestResponseEntry');

        $validator->after(function (Validator $validator) use ($record): void {
            $churchId = $this->integer('church_id');
            $branchId = $this->integer('branch_id');
            $recordedByUserId = $this->integer('recorded_by_user_id');
            $churchUnitIds = array_values(array_filter(array_map('intval', $this->input('church_unit_ids', []))));

            if (! $churchId) {
                return;
            }

            if ((int) $record->church_id !== $churchId) {
                $validator->errors()->add('church_id', 'Update the entry within the same church.');
                return;
            }

            if ($branchId) {
                $branchBelongsToChurch = Branch::query()
                    ->whereKey($branchId)
                    ->where('created_by_church_id', $churchId)
                    ->exists();

                if (! $branchBelongsToChurch) {
                    $validator->errors()->add('branch_id', 'Select a branch that belongs to this church.');
                }
            }

            if ($recordedByUserId) {
                $userBelongsToChurch = User::query()
                    ->whereKey($recordedByUserId)
                    ->where('church_id', $churchId)
                    ->exists();

                if (! $userBelongsToChurch) {
                    $validator->errors()->add('recorded_by_user_id', 'Select a user that belongs to this church.');
                }
            }

            if (! empty($churchUnitIds)) {
                $validUnitCount = ChurchUnit::query()
                    ->where('church_id', $churchId)
                    ->whereIn('id', $churchUnitIds)
                    ->count();

                if ($validUnitCount !== count(array_unique($churchUnitIds))) {
                    $validator->errors()->add('church_unit_ids', 'Select church units that belong to this church.');
                }
            }

            if ($this->boolean('wofbi_completed') && ! $this->filled('wofbi_level') && empty($this->input('wofbi_levels', []))) {
                $validator->errors()->add('wofbi_levels', 'Select at least one completed WOFBI level.');
            }
        });
    }
}
