<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignLeadRequest extends FormRequest
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
            'partner_id' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->resolveCompany() === null) {
                $validator->errors()->add('partner_id', 'Partner non valido.');
            }
        });
    }

    public function resolveCompany(): ?Company
    {
        $partnerId = $this->input('partner_id');

        if (is_numeric($partnerId)) {
            return Company::query()->find((int) $partnerId);
        }

        if (is_string($partnerId)) {
            return Company::query()->where('uuid', $partnerId)->first();
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partner_id.required' => 'Il partner è obbligatorio.',
        ];
    }
}
