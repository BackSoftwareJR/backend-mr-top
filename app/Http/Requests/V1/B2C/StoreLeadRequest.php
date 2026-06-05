<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2C;

use App\Enums\ConsentType;
use App\Services\ConsentLogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload');
        if (! is_array($payload) || ! is_array($payload['contact'] ?? null)) {
            return;
        }

        $email = $payload['contact']['email'] ?? null;
        if (is_string($email) && trim($email) === '') {
            unset($payload['contact']['email']);
            $this->merge(['payload' => $payload]);
        }
    }

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
            'sector_slug' => ['required', 'string', 'max:64', Rule::exists('sectors', 'slug')],
            'payload' => ['required', 'array'],
            'payload.autonomy' => [
                'required',
                'string',
                Rule::in(['autosufficiente', 'parziale', 'non-autosufficiente']),
            ],
            'payload.location' => ['required', 'array'],
            'payload.location.label' => ['required', 'string', 'min:2', 'max:128'],
            'payload.location.value' => [
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/^[a-z0-9-]+$/',
            ],
            'payload.interest_areas' => ['sometimes', 'array', 'max:10'],
            'payload.interest_areas.*.type' => [
                'required_with:payload.interest_areas',
                'string',
                Rule::in(['circle', 'polygon']),
            ],
            'payload.interest_areas.*.center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'payload.interest_areas.*.center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'payload.interest_areas.*.radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:80'],
            'payload.interest_areas.*.geometry' => ['nullable', 'array'],
            'payload.interest_areas.*.label' => ['nullable', 'string', 'max:255'],
            'payload.budget' => ['required', 'array'],
            'payload.budget.min' => ['required', 'integer', 'min:500', 'max:4900'],
            'payload.budget.max' => ['required', 'integer', 'min:600', 'max:5000'],
            'payload.contact' => ['required', 'array'],
            'payload.contact.nome' => ['required', 'string', 'min:1', 'max:128'],
            'payload.contact.telefono' => [
                'required',
                'string',
                'min:8',
                'max:32',
                'regex:/^\+?[0-9\s\-]{8,32}$/',
            ],
            'payload.contact.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'consent' => ['required', 'array'],
            'consent.privacy_accepted' => ['required', 'accepted'],
            'consent.terms_accepted' => ['required', 'accepted'],
            'consent.lead_sharing_accepted' => ['required', 'accepted'],
            'consent.marketing_accepted' => ['sometimes', 'boolean'],
            'consent_text_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'terms_text_hash' => ['sometimes', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'lead_sharing_text_hash' => ['sometimes', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'policy_version' => ['sometimes', 'string', 'regex:/^\d+\.\d+\.\d+$/', 'max:20'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = $this->input('payload.budget.min');
            $max = $this->input('payload.budget.max');

            if ($min !== null && $max !== null && (int) $max < (int) $min + 100) {
                $validator->errors()->add(
                    'payload.budget.max',
                    'Il budget massimo deve essere almeno 100€ superiore al minimo.',
                );
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $interestAreas = $this->input('payload.interest_areas');

            if (is_array($interestAreas)) {
                foreach ($interestAreas as $index => $area) {
                    if (! is_array($area)) {
                        continue;
                    }

                    $type = $area['type'] ?? null;

                    if ($type === 'circle') {
                        if (! isset($area['center_lat'], $area['center_lng'], $area['radius_km'])) {
                            $validator->errors()->add(
                                "payload.interest_areas.{$index}",
                                'Le aree circolari richiedono centro e raggio.',
                            );
                        }
                    }

                    if ($type === 'polygon' && ! is_array($area['geometry'] ?? null)) {
                        $validator->errors()->add(
                            "payload.interest_areas.{$index}",
                            'Le aree poligonali richiedono una geometria valida.',
                        );
                    }
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sessionId = $this->input('session_id') ?? $this->session()->getId();
            $policyVersion = $this->input(
                'policy_version',
                config('wenando.privacy_policy_version', '1.0.0'),
            );
            $consentLogService = app(ConsentLogService::class);

            foreach ([
                ConsentType::PrivacyPolicy,
                ConsentType::TermsB2c,
                ConsentType::LeadSharing,
            ] as $consentType) {
                if (! $consentLogService->hasValidConsent(
                    $consentType,
                    $this->user()?->id,
                    $sessionId,
                    $policyVersion,
                )) {
                    $validator->errors()->add(
                        'consent',
                        'Registra i consensi privacy, Condizioni B2C e condivisione partner prima di inviare la richiesta.',
                    );

                    break;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_slug.required' => 'Seleziona un settore valido.',
            'sector_slug.exists' => 'Settore non trovato.',
            'payload.required' => 'I dati del wizard sono obbligatori.',
            'payload.autonomy.required' => 'Indica il livello di autonomia.',
            'payload.autonomy.in' => 'Livello di autonomia non valido.',
            'payload.location.required' => 'La località è obbligatoria.',
            'payload.location.label.required' => 'La località è obbligatoria.',
            'payload.location.value.required' => 'Seleziona una località valida.',
            'payload.location.value.regex' => 'Formato località non valido.',
            'payload.budget.required' => 'Il budget è obbligatorio.',
            'payload.budget.min.required' => 'Indica un budget minimo.',
            'payload.budget.max.required' => 'Indica un budget massimo.',
            'payload.contact.required' => 'I dati di contatto sono obbligatori.',
            'payload.contact.nome.required' => 'Il nome è obbligatorio.',
            'payload.contact.telefono.required' => 'Il telefono è obbligatorio.',
            'payload.contact.telefono.regex' => 'Formato telefono non valido.',
            'consent.privacy_accepted.required' => 'Devi accettare l\'informativa privacy per inviare la richiesta.',
            'consent.privacy_accepted.accepted' => 'Devi accettare l\'informativa privacy per inviare la richiesta.',
            'consent.terms_accepted.required' => 'Devi accettare le Condizioni Generali B2C per inviare la richiesta.',
            'consent.terms_accepted.accepted' => 'Devi accettare le Condizioni Generali B2C per inviare la richiesta.',
            'consent.lead_sharing_accepted.required' => 'Devi acconsentire alla condivisione con i partner per inviare la richiesta.',
            'consent.lead_sharing_accepted.accepted' => 'Devi acconsentire alla condivisione con i partner per inviare la richiesta.',
            'consent_text_hash.required' => 'Hash del testo consenso obbligatorio.',
            'terms_text_hash.regex' => 'Hash del testo Condizioni B2C non valido.',
            'lead_sharing_text_hash.regex' => 'Hash del testo condivisione partner non valido.',
            'consent_text_hash.regex' => 'Hash del testo consenso non valido.',
        ];
    }
}
