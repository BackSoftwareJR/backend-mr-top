<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\CrmStatus;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Support\MarketplaceRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CRM client derived from an unlocked lead match.
 */
class CrmClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeadMatch $match */
        $match = $this->resource;
        /** @var Lead $lead */
        $lead = $match->lead;

        return [
            'id' => $match->public_ref ?? MarketplaceRef::crmClientId($match->id),
            'cliente' => $lead->contact_name,
            'stato' => $this->crmStatusLabel($match->crm_status),
            'esigenza' => $lead->need_summary,
            'budget' => $this->formatBudget($lead),
            'ultima_azione' => $this->ultimaAzione($match),
            'phone' => $lead->contact_phone,
            'email' => $lead->contact_email,
            'location' => $lead->location_label,
            'marketplace_id' => $match->public_ref ?? MarketplaceRef::fromMatchId($match->id),
        ];
    }

    private function formatBudget(Lead $lead): ?string
    {
        if ($lead->budget_min === null && $lead->budget_max === null) {
            return null;
        }

        $avg = (int) round((($lead->budget_min ?? 0) + ($lead->budget_max ?? 0)) / 2);

        return number_format($avg, 0, ',', '.').'€';
    }

    private function crmStatusLabel(?CrmStatus $status): string
    {
        return match ($status) {
            CrmStatus::Nuovo, null => 'Nuovo',
            CrmStatus::Contattato => 'Contattato',
            CrmStatus::VisitaFissata => 'Visita Fissata',
            CrmStatus::Perso => 'Perso',
            CrmStatus::Chiuso => 'Chiuso',
        };
    }

    private function ultimaAzione(LeadMatch $match): string
    {
        $timestamp = $match->updated_at ?? $match->unlocked_at;

        if ($timestamp === null) {
            return '—';
        }

        $date = $timestamp->locale('it')->isoFormat('D MMM');

        return match ($match->crm_status) {
            CrmStatus::Contattato => "Contattato · {$date}",
            CrmStatus::VisitaFissata => "Visita fissata · {$date}",
            CrmStatus::Perso => "Perso · {$date}",
            CrmStatus::Chiuso => "Chiuso · {$date}",
            default => "Lead sbloccato · {$date}",
        };
    }
}
