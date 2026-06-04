<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyProfileRequest extends FormRequest
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
            'company_id' => ['prohibited'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:512'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'pros' => ['sometimes', 'nullable', 'array', 'max:20'],
            'pros.*' => ['string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_hint' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.prohibited' => 'Non puoi specificare un\'altra azienda.',
            'display_name.max' => 'Il nome visualizzato è troppo lungo.',
            'tagline.max' => 'Il tagline è troppo lungo.',
            'pros.max' => 'Puoi inserire al massimo 20 punti di forza.',
            'pros.*.max' => 'Ogni punto di forza è troppo lungo.',
            'image_url.url' => 'L\'URL dell\'immagine non è valido.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        return $this->only([
            'display_name',
            'tagline',
            'description',
            'pros',
            'image_url',
            'location_label',
            'contact_hint',
        ]);
    }
}
