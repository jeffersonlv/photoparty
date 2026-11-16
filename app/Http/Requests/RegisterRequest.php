<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Nome do campo honeypot (invisível para humanos; robôs costumam preencher).
     */
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:'.User::class.',email', new NotDisposableEmail],
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            self::HONEYPOT => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('app.fields.name'),
            'email' => __('app.fields.email'),
            'password' => __('app.fields.password'),
            'terms' => __('app.fields.terms'),
        ];
    }

    public function messages(): array
    {
        return [
            self::HONEYPOT.'.prohibited' => __('app.errors.registration_rejected'),
        ];
    }
}
