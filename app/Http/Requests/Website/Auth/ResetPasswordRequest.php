<?php

namespace App\Http\Requests\Website\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }
}
