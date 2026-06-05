<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCoverageZoneRequest extends FormRequest
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
            'center_lat' => ['required', 'numeric', 'between:-90,90'],
            'center_lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['required', 'numeric', 'min:0.5', 'max:80'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'geocode_place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'geocode_meta' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.prohibited' => 'Non puoi specificare un\'altra azienda.',
            'center_lat.required' => 'La latitudine del centro è obbligatoria.',
            'center_lat.between' => 'La latitudine deve essere compresa tra -90 e 90.',
            'center_lng.required' => 'La longitudine del centro è obbligatoria.',
            'center_lng.between' => 'La longitudine deve essere compresa tra -180 e 180.',
            'radius_km.required' => 'Il raggio in chilometri è obbligatorio.',
            'radius_km.min' => 'Il raggio minimo è 0,5 km.',
            'radius_km.max' => 'Il raggio massimo è 80 km.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function zoneAttributes(): array
    {
        return $this->only([
            'center_lat',
            'center_lng',
            'radius_km',
            'label',
            'geocode_place_id',
            'geocode_meta',
        ]);
    }
}
