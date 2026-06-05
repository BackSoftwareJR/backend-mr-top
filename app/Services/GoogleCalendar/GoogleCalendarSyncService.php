<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use App\Exceptions\ApiException;
use App\Models\Appointment;
use App\Models\CalendarSyncEvent;
use App\Models\Company;
use App\Models\GoogleCalendarConnection;
use App\Support\CentralLog;

class GoogleCalendarSyncService
{
    /**
     * Push a local appointment to Google Calendar.
     *
     * @return array{synced: bool, google_event_id: string|null}
     */
    public function pushAppointment(Appointment $appointment): array
    {
        $connection = $this->resolveConnection($appointment->company_id);

        CentralLog::calendar('google.sync.push_requested', [
            'appointment_id' => $appointment->id,
            'company_id' => $appointment->company_id,
        ]);

        throw new ApiException(
            'GOOGLE_SYNC_NOT_IMPLEMENTED',
            'Sincronizzazione push verso Google Calendar non ancora implementata.',
            501,
        );
    }

    /**
     * Pull changes from Google Calendar for a company.
     *
     * @return array{pulled: int}
     */
    public function pullForCompany(Company $company): array
    {
        $this->resolveConnection($company->id);

        CentralLog::calendar('google.sync.pull_requested', [
            'company_id' => $company->id,
        ]);

        throw new ApiException(
            'GOOGLE_SYNC_NOT_IMPLEMENTED',
            'Sincronizzazione pull da Google Calendar non ancora implementata.',
            501,
        );
    }

    /**
     * @return array{sync_event: CalendarSyncEvent|null}
     */
    public function getSyncState(Appointment $appointment): array
    {
        $syncEvent = CalendarSyncEvent::query()
            ->where('appointment_id', $appointment->id)
            ->first();

        return [
            'sync_event' => $syncEvent,
        ];
    }

    private function resolveConnection(?int $companyId): GoogleCalendarConnection
    {
        if ($companyId === null) {
            throw new ApiException('GOOGLE_NOT_CONNECTED', 'Google Calendar non collegato.', 404);
        }

        $connection = GoogleCalendarConnection::query()
            ->where('company_id', $companyId)
            ->where('sync_enabled', true)
            ->first();

        if ($connection === null) {
            throw new ApiException('GOOGLE_NOT_CONNECTED', 'Google Calendar non collegato.', 404);
        }

        return $connection;
    }
}
