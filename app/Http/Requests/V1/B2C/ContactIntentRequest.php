<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2C;

use App\Enums\ConsentType;
use App\Services\ConsentLogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContactIntentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $contact = $this->input('contact');
        if (! is_array($contact)) {
            return;
        }

        $email = $contact['email'] ?? null;
        if (is_string($email) && trim($email) === '') {
            unset($contact['email']);
            $this->merge(['contact' => $contact]);
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
            'query' => ['required', 'string', 'max:500'],
            'selections' => ['sometimes', 'array'],
            'refinementHistory' => ['sometimes', 'array'],
            'activePathId' => ['sometimes', 'nullable', 'string', 'max:128'],
            'sector_slug' => ['sometimes', 'string', 'max:64', Rule::exists('sectors', 'slug')],
            'contact' => ['required', 'array'],
            'contact.nome' => ['required', 'string', 'min:1', 'max:128'],
            'contact.telefono' => [
                'required',
                'string',
                'min:8',
                'max:32',
                'regex:/^\+?[0-9\s\-]{8,32}$/',
            ],
            'contact.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'interest_areas' => ['sometimes', 'array', 'max:10'],
            'interest_areas.*.type' => [
                'required_with:interest_areas',
                'string',
                Rule::in(['circle', 'polygon']),
            ],
            'interest_areas.*.center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'interest_areas.*.center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'interest_areas.*.radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:80'],
            'interest_areas.*.geometry' => ['nullable', 'array'],
            'interest_areas.*.label' => ['nullable', 'string', 'max:255'],
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
            'explore_session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sessionId = $this->input('session_id')
                ?? ($this->hasSession() ? $this->session()->getId() : null);
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
            'query.required' => 'La ricerca di partenza è obbligatoria.',
            'contact.required' => 'I dati di contatto sono obbligatori.',
            'contact.nome.required' => 'Il nome è obbligatorio.',
            'contact.telefono.required' => 'Il telefono è obbligatorio.',
            'contact.telefono.regex' => 'Formato telefono non valido.',
            'consent.privacy_accepted.accepted' => 'Devi accettare l\'informativa privacy per procedere.',
            'consent.terms_accepted.accepted' => 'Devi accettare le Condizioni Generali B2C per procedere.',
            'consent.lead_sharing_accepted.accepted' => 'Devi acconsentire alla condivisione con i partner per procedere.',
        ];
    }
}
