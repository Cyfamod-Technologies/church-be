<?php

namespace App\Http\Requests\Api;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChurchProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');
        $adminId = $this->input('admin.id');

        return [
            'church.name' => ['required', 'string', 'max:255'],
            'church.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('churches', 'code')->ignore($church->id),
            ],
            'church.address' => ['nullable', 'string', 'max:255'],
            'church.city' => ['nullable', 'string', 'max:100'],
            'church.state' => ['nullable', 'string', 'max:100'],
            'church.district_area' => ['nullable', 'string', 'max:100'],
            'church.email' => ['nullable', 'email', 'max:255'],
            'church.phone' => ['nullable', 'string', 'max:30'],
            'church.status' => ['nullable', 'string', 'max:50'],

            'pastor.name' => ['required', 'string', 'max:255'],
            'pastor.phone' => ['required', 'string', 'max:30'],
            'pastor.email' => ['nullable', 'email', 'max:255'],

            'settings.finance_enabled' => ['required', 'boolean'],

            'admin.id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($adminId),
            ],
            'admin.phone' => ['required', 'string', 'max:30'],
            'admin.password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
