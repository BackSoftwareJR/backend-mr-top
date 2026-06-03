<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPartnerRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
