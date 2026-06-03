<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use App\Enums\CrmStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmClientRequest extends FormRequest
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
            'stato' => [
                'required',
                'string',
                Rule::in([
                    'Nuovo',
                    'Contattato',
                    'Visita Fissata',
                    'Perso',
                    'Chiuso',
                    'nuovo',
                    'contattato',
                    'visita_fissata',
                    'perso',
                    'chiuso',
                ]),
            ],
        ];
    }

    public function crmStatus(): CrmStatus
    {
        return match ($this->string('stato')->toString()) {
            'Nuovo', 'nuovo' => CrmStatus::Nuovo,
            'Contattato', 'contattato' => CrmStatus::Contattato,
            'Visita Fissata', 'visita_fissata' => CrmStatus::VisitaFissata,
            'Perso', 'perso' => CrmStatus::Perso,
            'Chiuso', 'chiuso' => CrmStatus::Chiuso,
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stato.required' => 'Lo stato CRM è obbligatorio.',
            'stato.in' => 'Stato CRM non valido.',
        ];
    }
}
