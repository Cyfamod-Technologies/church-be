<?php

namespace App\Http\Requests\Api;

use App\Models\Church;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $churchId = $this->input('church_id');

        return [
            'church_id' => ['nullable', 'integer', Rule::exists(Church::class, 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('branch_tags', 'name')->where(function ($query) use ($churchId) {
                    return $query->where('church_id', $churchId);
                }),
            ],
        ];
    }
}
