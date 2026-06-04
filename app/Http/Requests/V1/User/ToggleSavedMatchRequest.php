<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ToggleSavedMatchRequest extends FormRequest
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
            'company_id' => ['nullable', 'integer', 'exists:companies,id', 'required_without:lead_match_id'],
            'lead_match_id' => ['nullable', 'integer', 'exists:lead_matches,id', 'required_without:company_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required_without' => 'Specificare company_id o lead_match_id.',
            'lead_match_id.required_without' => 'Specificare company_id o lead_match_id.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('company_id') && $this->filled('lead_match_id')) {
                $validator->errors()->add(
                    'company_id',
                    'Specificare solo company_id o lead_match_id, non entrambi.',
                );
            }
        });
    }

    public function companyId(): ?int
    {
        return $this->filled('company_id') ? (int) $this->validated('company_id') : null;
    }

    public function leadMatchId(): ?int
    {
        return $this->filled('lead_match_id') ? (int) $this->validated('lead_match_id') : null;
    }
}
