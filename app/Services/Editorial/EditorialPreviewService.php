<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Models\EditorialContent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class EditorialPreviewService
{
    /**
     * @return list<EditorialContentStatus>
     */
    public function previewableStatuses(): array
    {
        return [
            EditorialContentStatus::Draft,
            EditorialContentStatus::PendingReview,
            EditorialContentStatus::Scheduled,
        ];
    }

    public function isPreviewable(EditorialContent $content): bool
    {
        return in_array($content->status, $this->previewableStatuses(), true);
    }

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function generate(string $contentUuid): array
    {
        $expiresAt = CarbonImmutable::now()->addHours($this->ttlHours());

        $payload = $this->encodePayload($contentUuid, $expiresAt);
        $signature = $this->sign($payload);

        return [
            'token' => $payload.'.'.$signature,
            'expires_at' => $expiresAt,
        ];
    }

    public function validate(string $contentUuid, string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$payload, $signature] = $parts;

        if ($payload === '' || $signature === '') {
            return false;
        }

        if (! hash_equals($this->sign($payload), $signature)) {
            return false;
        }

        $decoded = $this->decodePayload($payload);
        if ($decoded === null) {
            return false;
        }

        if ($decoded['content_uuid'] !== $contentUuid) {
            return false;
        }

        return $decoded['expires_at'] > now()->getTimestamp();
    }

    public function previewUrl(string $contentUuid, string $token): string
    {
        $path = '/preview/editorial/'.$contentUuid.'?token='.urlencode($token);

        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');

        return $siteUrl !== '' ? $siteUrl.$path : $path;
    }

    private function ttlHours(): int
    {
        return max(1, (int) config('editorial.preview_ttl_hours', 24));
    }

    private function secret(): string
    {
        $secret = config('editorial.preview_secret');

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $appKey = (string) config('app.key');

        if (Str::startsWith($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            return $decoded !== false ? $decoded : $appKey;
        }

        return $appKey;
    }

    private function encodePayload(string $contentUuid, CarbonImmutable $expiresAt): string
    {
        $json = json_encode([
            'content_uuid' => $contentUuid,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array{content_uuid: string, expires_at: int}|null
     */
    private function decodePayload(string $payload): ?array
    {
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $contentUuid = $data['content_uuid'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (! is_string($contentUuid) || $contentUuid === '' || ! is_int($expiresAt)) {
            return null;
        }

        return [
            'content_uuid' => $contentUuid,
            'expires_at' => $expiresAt,
        ];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret());
    }
}
