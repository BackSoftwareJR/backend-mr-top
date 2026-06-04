<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvisorBookingRequest extends FormRequest
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
            'scheduled_date.required' => 'La data è obbligatoria.',
            'scheduled_date.after' => 'Seleziona una data futura.',
            'scheduled_time.required' => 'L\'orario è obbligatorio.',
            'scheduled_time.regex' => 'Formato orario non valido.',
        ];
    }
}
