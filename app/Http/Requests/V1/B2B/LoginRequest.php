<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['sometimes', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'email è obbligatoria.',
            'email.email' => 'Formato email non valido.',
            'password.required' => 'La password è obbligatoria.',
            'password.min' => 'La password deve contenere almeno 8 caratteri.',
        ];
    }
}
