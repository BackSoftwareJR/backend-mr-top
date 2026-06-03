<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enums\OtpPortal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpRequestRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'portal' => ['required', Rule::in(OtpPortal::values())],
            'captcha' => ['required', 'array'],
            'captcha.honeypot' => ['nullable', 'string', 'max:0'],
            'captcha.human_confirmed' => ['required', 'boolean', Rule::in([true, 1])],
            'captcha.form_started_at' => ['required', 'integer'],
            'captcha.challenge_answer' => ['nullable', 'string'],
            'captcha.expected_challenge' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $started = (int) $this->input('captcha.form_started_at', 0);
            if ($started > 0 && (now()->getTimestampMs() - $started) < 2000) {
                $validator->errors()->add('captcha', 'Verifica non superata.');
            }

            $answer = $this->input('captcha.challenge_answer');
            $expected = $this->input('captcha.expected_challenge');
            if ($expected !== null && $answer !== $expected) {
                $validator->errors()->add('captcha', 'Verifica non superata.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower((string) $this->input('email'))]);
        }
    }
}
