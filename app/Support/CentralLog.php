<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AppLogChannel;
use App\Models\AppLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CentralLog
{
    public const JSON_CHANNEL = 'json';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function api(string $message, array $context = [], string $level = 'info', ?Throwable $exception = null): void
    {
        self::write(AppLogChannel::Api, $level, $message, $context, $exception);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function webhook(string $message, array $context = [], string $level = 'info', ?Throwable $exception = null): void
    {
        self::write(AppLogChannel::Webhook, $level, $message, $context, $exception);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function calendar(string $message, array $context = [], string $level = 'info', ?Throwable $exception = null): void
    {
        self::write(AppLogChannel::Calendar, $level, $message, $context, $exception);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function b2b(string $message, array $context = [], string $level = 'info', ?Throwable $exception = null): void
    {
        self::write(AppLogChannel::B2b, $level, $message, $context, $exception);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function job(string $message, array $context = [], string $level = 'info', ?Throwable $exception = null): void
    {
        self::write(AppLogChannel::Job, $level, $message, $context, $exception);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(
        AppLogChannel $channel,
        string $level,
        string $message,
        array $context,
        ?Throwable $exception,
    ): void {
        $requestId = self::resolveRequestId();
        $userId = Auth::id();
        $companyId = self::resolveCompanyId();

        $payload = array_merge([
            'channel' => $channel->value,
            'level' => $level,
            'request_id' => $requestId,
            'user_id' => $userId,
            'company_id' => $companyId,
            'message' => $message,
        ], $context);

        if ($exception !== null) {
            $payload['exception_class'] = $exception::class;
            $payload['exception_message'] = $exception->getMessage();
        }

        Log::channel(self::JSON_CHANNEL)->log($level, $message, $payload);

        if (! self::shouldPersist($channel, $level)) {
            return;
        }

        AppLog::query()->create([
            'request_id' => $requestId,
            'user_id' => $userId,
            'company_id' => $companyId,
            'channel' => $channel,
            'level' => $level,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
            'exception' => $exception !== null ? self::formatException($exception) : null,
            'created_at' => now(),
        ]);
    }

    private static function shouldPersist(AppLogChannel $channel, string $level): bool
    {
        if ($channel !== AppLogChannel::Api) {
            return true;
        }

        return in_array($level, ['error', 'warning', 'critical', 'alert', 'emergency'], true);
    }

    private static function resolveRequestId(): ?string
    {
        $request = request();
        $id = $request?->attributes->get('request_id');

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $shared = Log::sharedContext()['request_id'] ?? null;

        return is_string($shared) && $shared !== '' ? $shared : null;
    }

    private static function resolveCompanyId(): ?int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $companyId = $user->companies()->value('companies.id');

        return is_int($companyId) ? $companyId : null;
    }

    private static function formatException(Throwable $exception): string
    {
        return sprintf(
            "%s: %s\n%s",
            $exception::class,
            $exception->getMessage(),
            $exception->getTraceAsString(),
        );
    }
}
