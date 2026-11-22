<?php
namespace App\Http\Requests\Admin\Auth;
use Illuminate\Foundation\Http\FormRequest;
class SendOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['email' => ['required','email:rfc','max:255']]; }
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }
}
