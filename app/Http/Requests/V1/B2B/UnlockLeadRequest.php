<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class UnlockLeadRequest extends FormRequest
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
            'idempotency_key' => [
                'sometimes',
                'string',
                'uuid',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->header('Idempotency-Key') !== null) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }
    }
}
