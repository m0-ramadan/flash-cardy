<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialMediaLoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['google', 'facebook', 'apple'])],
            'auth_source' => ['nullable', Rule::in(['firebase', 'google', 'facebook', 'apple'])],
            'id_token' => ['nullable', 'string', 'max:10000'],
            'access_token' => ['nullable', 'string', 'max:10000'],
            'authorization_code' => ['nullable', 'string', 'max:10000'],
            'name' => ['nullable', 'string', 'max:255'],
            // Legacy fields stay accepted during the transition, but the backend never trusts them.
            'provider_id' => ['nullable', 'string', 'max:512'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'image' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
