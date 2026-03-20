<?php

namespace App\Http\Requests\Api;

use App\Models\Branch;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_parent_church_id' => ['nullable', 'integer', Rule::exists(Church::class, 'id')],
            'to_parent_branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')],
            'changed_by_church_id' => ['required', 'integer', Rule::exists(Church::class, 'id')],
            'changed_by_user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'changed_by_actor_type' => ['required', 'string', 'in:church,user'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $churchId = $this->input('to_parent_church_id');
                $branchId = $this->input('to_parent_branch_id');

                if (!$churchId && !$branchId) {
                    $validator->errors()->add('to_parent_church_id', 'Select a church or branch to assign under.');
                }

                if ($churchId && $branchId) {
                    $validator->errors()->add('to_parent_branch_id', 'Assign under either a church or a branch, not both.');
                }
            },
        ];
    }
}
