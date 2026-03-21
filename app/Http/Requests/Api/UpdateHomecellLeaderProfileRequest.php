<?php

namespace App\Http\Requests\Api;

use App\Models\HomecellLeader;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateHomecellLeaderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var HomecellLeader $homecellLeader */
        $homecellLeader = $this->route('homecellLeader');
        $ignoreUserId = $homecellLeader->user_id;

        $validator->after(function (Validator $validator) use ($ignoreUserId): void {
            $email = trim((string) $this->input('email', ''));
            $phone = trim((string) $this->input('phone', ''));

            if ($email !== '' && User::query()
                ->where('email', $email)
                ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
                ->exists()) {
                $validator->errors()->add('email', 'That email address is already in use by another user account.');
            }

            if ($phone !== '' && User::query()
                ->where('phone', $phone)
                ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
                ->exists()) {
                $validator->errors()->add('phone', 'That phone number is already in use by another user account.');
            }
        });
    }
}
