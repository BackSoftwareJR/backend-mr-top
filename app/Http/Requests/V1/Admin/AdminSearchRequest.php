<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Inserisci almeno 2 caratteri per la ricerca.',
            'q.min' => 'Inserisci almeno 2 caratteri per la ricerca.',
            'q.max' => 'La ricerca non può superare 100 caratteri.',
        ];
    }
}
