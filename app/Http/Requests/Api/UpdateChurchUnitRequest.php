<?php

namespace App\Http\Requests\Api;

use App\Models\ChurchUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChurchUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ChurchUnit $churchUnit */
        $churchUnit = $this->route('churchUnit');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('church_units', 'code')->ignore($churchUnit->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
