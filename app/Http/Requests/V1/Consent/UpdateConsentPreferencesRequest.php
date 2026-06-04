<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Consent;

use App\Enums\ConsentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsentPreferencesRequest extends FormRequest
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
            'preferences' => ['required', 'array', 'min:1', 'max:2'],
            'preferences.*.consent_type' => [
                'required',
                'string',
                Rule::in([
                    ConsentType::Marketing->value,
                    ConsentType::AnalyticsCookies->value,
                ]),
            ],
            'preferences.*.consent_given' => ['required', 'boolean'],
            'preferences.*.consent_text_hash' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
            'preferences.*.policy_version' => [
                'required',
                'string',
                'regex:/^\d+\.\d+\.\d+$/',
                'max:20',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferences.required' => 'Almeno una preferenza è obbligatoria.',
            'preferences.*.consent_type.in' => 'Tipo di consenso non revocabile tramite preferenze.',
            'preferences.*.consent_text_hash.regex' => 'Hash del testo consenso non valido.',
            'preferences.*.policy_version.regex' => 'La versione policy deve essere nel formato semver (es. 1.0.0).',
        ];
    }
}
