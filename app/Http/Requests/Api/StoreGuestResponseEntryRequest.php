<?php

namespace App\Http\Requests\Api;

use App\Models\Branch;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGuestResponseEntryRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $churchId = $this->integer('church_id');
            $branchId = $this->integer('branch_id');
            $recordedByUserId = $this->integer('recorded_by_user_id');

            if (! $churchId) {
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
        });
    }
}
