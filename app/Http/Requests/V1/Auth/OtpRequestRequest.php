<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enums\OtpPortal;
use App\Services\CaptchaVerificationService;
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
        $rules = [
            'email' => ['required', 'email', 'max:255'],
            'portal' => ['required', Rule::in(OtpPortal::values())],
        ];

        if ($this->captchaVerification()->isRequired()) {
            $rules['captcha_token'] = ['required', 'string'];

            return $rules;
        }

        $rules['captcha'] = ['required', 'array'];
        $rules['captcha.honeypot'] = ['nullable', 'string', 'max:0'];
        $rules['captcha.human_confirmed'] = ['required', 'boolean', Rule::in([true, 1])];
        $rules['captcha.form_started_at'] = ['required', 'integer'];
        $rules['captcha.challenge_answer'] = ['nullable', 'string'];
        $rules['captcha.expected_challenge'] = ['nullable', 'string'];

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->captchaVerification()->isRequired()) {
                $verified = $this->captchaVerification()->verify(
                    $this->string('captcha_token')->toString(),
                    $this->ip(),
                );

                if (! $verified) {
                    $validator->errors()->add('captcha_token', 'Verifica captcha non superata.');
                }

                return;
            }

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

    private function captchaVerification(): CaptchaVerificationService
    {
        return app(CaptchaVerificationService::class);
    }
}
