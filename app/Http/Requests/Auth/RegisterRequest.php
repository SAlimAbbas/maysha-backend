<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;
use App\Rules\NotDisposableEmail;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email', new NotDisposableEmail()],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:0'],
            'bot_trap' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('website') || $this->filled('bot_trap')) {
                $validator->errors()->add('email', 'Submission rejected by bot protection filter.');
            }
        });
    }
}
