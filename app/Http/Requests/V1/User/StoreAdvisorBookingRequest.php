<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvisorBookingRequest extends FormRequest
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
            'lead_uuid' => ['nullable', 'uuid', Rule::exists('leads', 'uuid')],
            'name' => ['required', 'string', 'max:128'],
            'scheduled_date' => ['required', 'date', 'after:today'],
            'scheduled_time' => [
                'required',
                'string',
                'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio.',
            'scheduled_date.required' => 'La data è obbligatoria.',
            'scheduled_date.after' => 'Seleziona una data futura.',
            'scheduled_time.required' => 'L\'orario è obbligatorio.',
            'scheduled_time.regex' => 'Formato orario non valido.',
            'lead_uuid.exists' => 'Lead non trovato.',
        ];
    }
}
