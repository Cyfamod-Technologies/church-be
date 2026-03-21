<?php

namespace App\Http\Requests\Api;

use App\Models\Church;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChurchUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists(Church::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('church_units', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
