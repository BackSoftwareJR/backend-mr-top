<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use App\Exceptions\ApiException;
use App\Models\Company;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Support\CentralLog;
use Illuminate\Support\Str;

class GoogleCalendarOAuthService
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.readonly',
    ];

    /**
     * @return array{url: string, state: string}
     */
    public function buildAuthorizationUrl(Company $company, User $user): array
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect_uri');

        if (! is_string($clientId) || $clientId === '' || ! is_string($redirectUri) || $redirectUri === '') {
            throw new ApiException(
                'GOOGLE_NOT_CONFIGURED',
                'Integrazione Google Calendar non configurata.',
                503,
            );
        }

        $state = (string) Str::ulid();
        session([
            'google_oauth_state' => $state,
            'google_oauth_company_id' => $company->id,
            'google_oauth_user_id' => $user->id,
        ]);

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        CentralLog::calendar('google.oauth.connect_initiated', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return [
            'url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$params,
            'state' => $state,
        ];
    }

    /**
     * @return array{connected: bool, message: string}
     */
    public function handleCallback(string $code, string $state): array
    {
        $expectedState = session('google_oauth_state');

        if (! is_string($expectedState) || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            throw new ApiException('GOOGLE_OAUTH_STATE_INVALID', 'State OAuth non valido.', 400);
        }

        CentralLog::calendar('google.oauth.callback_received', [
            'state' => $state,
        ]);

        throw new ApiException(
            'GOOGLE_OAUTH_NOT_IMPLEMENTED',
            'Scambio token Google Calendar non ancora implementato. Il callback OAuth è predisposto.',
            501,
        );
    }

    public function disconnect(Company $company): void
    {
        GoogleCalendarConnection::query()
            ->where('company_id', $company->id)
            ->delete();

        CentralLog::calendar('google.oauth.disconnected', [
            'company_id' => $company->id,
        ], 'info');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(Company $company): array
    {
        $connection = GoogleCalendarConnection::query()
            ->where('company_id', $company->id)
            ->first();

        if ($connection === null) {
            return [
                'connected' => false,
                'sync_enabled' => false,
                'calendar_id' => null,
                'connected_at' => null,
                'connected_by_user_id' => null,
            ];
        }

        return [
            'connected' => true,
            'sync_enabled' => $connection->sync_enabled,
            'calendar_id' => $connection->calendar_id,
            'connected_at' => $connection->connected_at?->toIso8601String(),
            'connected_by_user_id' => $connection->user_id,
            'scopes' => $connection->scopes ?? [],
        ];
    }
}
