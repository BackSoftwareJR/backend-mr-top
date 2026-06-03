<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use Illuminate\Foundation\Http\FormRequest;

class IndexMarketplaceRequest extends FormRequest
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
            'unlocked_only' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
