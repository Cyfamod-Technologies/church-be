<?php

namespace App\Http\Requests\Api;

use App\Models\Branch;
use App\Models\BranchTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');
        $localAdminId = $branch->localAdmin()->value('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)],
            'branch_tag_id' => ['required', 'integer', Rule::exists(BranchTag::class, 'id')],
            'pastor_name' => ['nullable', 'string', 'max:255'],
            'pastor_phone' => ['nullable', 'string', 'max:30'],
            'pastor_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'district_area' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:50'],
            'admin' => ['nullable', 'array'],
            'admin.name' => ['nullable', 'string', 'max:255'],
            'admin.email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($localAdminId)],
            'admin.phone' => ['nullable', 'string', 'max:30'],
            'admin.password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        $validator->after(function (Validator $validator) use ($branch): void {
            $admin = $this->input('admin');

            if (!is_array($admin)) {
                return;
            }

            $hasAdminValues = collect([
                data_get($admin, 'name'),
                data_get($admin, 'email'),
                data_get($admin, 'phone'),
                data_get($admin, 'password'),
            ])->contains(fn ($value) => filled($value));

            if (!$hasAdminValues) {
                return;
            }

            $localAdminExists = $branch->localAdmin()->exists();

            if (!$localAdminExists && blank(data_get($admin, 'name'))) {
                $validator->errors()->add('admin.name', 'Branch admin name is required when creating a branch admin.');
            }

            if (!$localAdminExists && blank(data_get($admin, 'email'))) {
                $validator->errors()->add('admin.email', 'Branch admin email is required when creating a branch admin.');
            }

            if (!$localAdminExists && blank(data_get($admin, 'password'))) {
                $validator->errors()->add('admin.password', 'Branch admin password is required when creating a branch admin.');
            }
        });
    }
}
