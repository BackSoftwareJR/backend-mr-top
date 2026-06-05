<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppointmentType;
use App\Enums\CrmStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\LeadMatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class B2bAppointmentService
{
    /**
     * @return Collection<int, Appointment>
     */
    public function list(Company $company, ?string $from = null, ?string $to = null): Collection
    {
        $query = Appointment::query()
            ->where('company_id', $company->id)
            ->with('leadMatch.lead')
            ->orderBy('scheduled_date');

        if ($from !== null) {
            $query->whereDate('scheduled_date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('scheduled_date', '<=', $to);
        }

        return $query->get();
    }

    /**
     * @return array{appointment: Appointment, client: LeadMatch}
     */
    public function schedule(
        Company $company,
        string $clientId,
        string $date,
        string $time,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use ($company, $clientId, $date, $time, $note): array {
            $matchId = (int) preg_replace('/\D/', '', $clientId);
            $leadMatch = LeadMatch::query()
                ->where('company_id', $company->id)
                ->whereKey($matchId)
                ->lockForUpdate()
                ->firstOrFail();

            $leadMatch->update(['crm_status' => CrmStatus::VisitaFissata]);

            $appointment = Appointment::query()->create([
                'company_id' => $company->id,
                'lead_match_id' => $leadMatch->id,
                'client_name' => $leadMatch->lead?->contact_name ?? 'Cliente',
                'scheduled_date' => $date,
                'scheduled_time' => $time,
                'note' => $note,
                'type' => AppointmentType::Visit,
            ]);

            return [
                'appointment' => $appointment,
                'client' => $leadMatch->fresh(['lead']),
            ];
        });
    }

    public function update(Company $company, int $appointmentId, array $data): Appointment
    {
        $appointment = Appointment::query()
            ->where('company_id', $company->id)
            ->whereKey($appointmentId)
            ->firstOrFail();

        $updates = [];
        if (array_key_exists('note', $data)) {
            $updates['note'] = $data['note'];
        }
        if (array_key_exists('checklist', $data)) {
            $updates['checklist'] = $data['checklist'];
        }
        if (array_key_exists('date', $data)) {
            $updates['scheduled_date'] = $data['date'];
        }
        if (array_key_exists('time', $data)) {
            $updates['scheduled_time'] = $data['time'];
        }

        if ($updates !== []) {
            $appointment->update($updates);
        }

        return $appointment->fresh();
    }
}
