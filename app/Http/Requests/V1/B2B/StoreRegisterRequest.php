<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegisterRequest extends FormRequest
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
            'organization_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'privacy_policy_accepted' => ['required', 'accepted'],
            'consent_text_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'policy_version' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privacy_policy_accepted.accepted' => 'Devi accettare l\'Informativa sulla Privacy per registrarti.',
            'consent_text_hash.required' => 'Hash del testo di consenso mancante.',
        ];
    }
}
