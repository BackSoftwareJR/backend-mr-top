<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use App\Enums\EditorialContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentEditorialDraftRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(EditorialContentType::class)],
            'rubric_slug' => ['required', 'string', 'max:80', 'exists:editorial_rubrics,slug'],
            'body_blocks' => ['required', 'array', 'min:1'],
            'body_blocks.*.id' => ['required', 'string', 'uuid'],
            'body_blocks.*.type' => ['required', 'string', Rule::in(self::BLOCK_TYPES)],
            'body_blocks.*.data' => ['required', 'array'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'external_ref' => ['nullable', 'string', 'max:120'],
            'auto_submit_review' => ['nullable', 'boolean'],
            'status' => ['prohibited'],
            'to_status' => ['prohibited'],
            'published' => ['prohibited'],
            'auto_publish' => ['prohibited'],
        ];
    }
}
