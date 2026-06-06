<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEditorialContentRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const BLOCK_TYPES = [
        'heading',
        'paragraph',
        'image',
        'quote',
        'callout',
        'faq',
        'cta',
        'embed',
        'structure_card',
        'related_links',
        'section_break',
        'event_details',
        'interview_qa',
    ];

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
            'content_type' => ['sometimes', Rule::enum(EditorialContentType::class)],
            'title' => ['sometimes', 'string', 'max:200'],
            'rubric_id' => ['sometimes', 'integer', 'exists:editorial_rubrics,id'],
            'body_blocks' => ['sometimes', 'array', 'min:1'],
            'body_blocks.*.id' => ['required_with:body_blocks', 'string', 'uuid'],
            'body_blocks.*.type' => ['required_with:body_blocks', 'string', Rule::in(self::BLOCK_TYPES)],
            'body_blocks.*.data' => ['required_with:body_blocks', 'array'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'excerpt' => ['nullable', 'string'],
            'seo_pack' => ['nullable', 'array'],
            'seo_pack.seo_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'seo_pack.approved' => ['nullable', 'boolean'],
            'seo_pack.focus_keyword' => ['nullable', 'string', 'max:80'],
            'seo_pack.geo_excerpt' => ['nullable', 'string', 'max:500'],
            'author_type' => ['sometimes', Rule::enum(EditorialAuthorType::class)],
            'author_name' => ['nullable', 'string', 'max:120'],
            'author_role_title' => ['nullable', 'string', 'max:120'],
            'company_id' => [
                'nullable',
                'required_if:author_type,'.EditorialAuthorType::Company->value,
                'integer',
                'exists:companies,id',
            ],
            'sector_id' => ['sometimes', 'integer', 'exists:sectors,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:60'],
            'type_payload' => ['nullable', 'array'],
            'featured' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:5'],
            'canonical_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
