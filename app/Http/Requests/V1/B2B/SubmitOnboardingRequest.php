<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOnboardingRequest extends FormRequest
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
            'terms_b2b_accepted' => ['required', 'accepted'],
            'terms_text_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'policy_version' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_b2b_accepted.accepted' => 'Devi accettare le Condizioni Generali Partner B2B per inviare il profilo.',
            'terms_text_hash.required' => 'Hash del testo di consenso mancante.',
        ];
    }
}
