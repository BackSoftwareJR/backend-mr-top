<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Admin;

use App\Enums\EditorialContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionEditorialContentRequest extends FormRequest
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
            'to_status' => ['required', Rule::enum(EditorialContentStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'updated_at' => ['nullable', 'date'],
        ];
    }
}
