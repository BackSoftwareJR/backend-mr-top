<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Il nome non può superare 255 caratteri.',
            'phone.max' => 'Il telefono non può superare 32 caratteri.',
        ];
    }

    /**
     * @return array{name?: string, phone?: string|null}
     */
    public function profileAttributes(): array
    {
        $attributes = [];

        if ($this->has('name')) {
            $attributes['name'] = $this->validated('name');
        }

        if ($this->has('phone')) {
            $attributes['phone'] = $this->validated('phone');
        }

        return $attributes;
    }
}
