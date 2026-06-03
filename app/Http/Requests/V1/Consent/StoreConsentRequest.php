<?php

namespace App\Http\Requests\V1\Consent;

use App\Enums\ConsentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsentRequest extends FormRequest
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
            'consents' => ['required', 'array', 'min:1', 'max:10'],
            'consents.*.consent_type' => ['required', 'string', Rule::in(ConsentType::values())],
            'consents.*.policy_version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/', 'max:20'],
            'consents.*.consent_given' => ['required', 'boolean'],
            'consents.*.consent_text_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'consents.*.session_id' => ['nullable', 'string', 'max:64'],
            'consents.*.lead_uuid' => ['nullable', 'uuid', Rule::exists('leads', 'uuid')],
            'consents.*.metadata' => ['nullable', 'array', 'max:20'],
            'consents.*.metadata.*' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consents.required' => 'Almeno un consenso è obbligatorio.',
            'consents.*.consent_type.in' => 'Tipo di consenso non valido.',
            'consents.*.policy_version.regex' => 'La versione policy deve essere nel formato semver (es. 1.0.0).',
            'consents.*.consent_text_hash.regex' => 'Hash del testo consenso non valido.',
            'consents.*.lead_uuid.exists' => 'Lead non trovato.',
        ];
    }
}
