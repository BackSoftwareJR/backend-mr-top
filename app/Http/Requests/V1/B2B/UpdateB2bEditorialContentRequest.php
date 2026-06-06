<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\B2B;

use App\Enums\EditorialContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateB2bEditorialContentRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const BLOCK_TYPES = [
        'heading',
        'paragraph',
        'image',
        'quote',
        'event_details',
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
            'type' => ['sometimes', Rule::enum(EditorialContentType::class)],
            'title' => ['sometimes', 'string', 'max:200'],
            'rubric_id' => ['sometimes', 'integer', 'exists:editorial_rubrics,id'],
            'body_blocks' => ['sometimes', 'array', 'min:1'],
            'body_blocks.*.id' => ['required_with:body_blocks', 'string', 'uuid'],
            'body_blocks.*.type' => ['required_with:body_blocks', 'string', Rule::in(self::BLOCK_TYPES)],
            'body_blocks.*.data' => ['required_with:body_blocks', 'array'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'excerpt' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        if (! is_array($validated)) {
            return [];
        }

        if (isset($validated['type'])) {
            $validated['content_type'] = $validated['type'];
            unset($validated['type']);
        }

        return $validated;
    }
}
