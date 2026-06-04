<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        AuditAction $action,
        ?User $actor = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata !== [] ? $metadata : null,
            'ip_address' => $request?->ip(),
        ]);
    }

    public function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return mb_strlen($userAgent) > 512
            ? mb_substr($userAgent, 0, 512)
            : $userAgent;
    }
}
