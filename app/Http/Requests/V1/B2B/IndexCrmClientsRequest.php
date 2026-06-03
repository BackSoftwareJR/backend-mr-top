<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use App\Enums\CrmStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCrmClientsRequest extends FormRequest
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
            'stato' => ['sometimes', 'string', Rule::in(CrmStatus::values())],
        ];
    }
}
