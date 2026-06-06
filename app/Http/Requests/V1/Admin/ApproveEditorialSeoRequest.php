<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveEditorialSeoRequest extends FormRequest
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
            'generation_id' => ['nullable', 'integer'],
            'manual_overrides' => ['nullable', 'array'],
            'manual_overrides.seo_title' => ['nullable', 'string', 'max:200'],
            'manual_overrides.seo_description' => ['nullable', 'string', 'max:500'],
            'manual_overrides.meta_description' => ['nullable', 'string', 'max:500'],
            'manual_overrides.excerpt' => ['nullable', 'string', 'max:2000'],
            'manual_overrides.og_title' => ['nullable', 'string', 'max:200'],
            'manual_overrides.og_description' => ['nullable', 'string', 'max:500'],
            'manual_overrides.primary_keyword' => ['nullable', 'string', 'max:120'],
            'manual_overrides.secondary_keywords' => ['nullable', 'array'],
            'manual_overrides.secondary_keywords.*' => ['string', 'max:120'],
            'manual_overrides.suggested_tags' => ['nullable', 'array'],
            'manual_overrides.suggested_tags.*' => ['string', 'max:60'],
        ];
    }
}
