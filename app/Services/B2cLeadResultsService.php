<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadMatch;
use Illuminate\Database\Eloquent\Collection;

class B2cLeadResultsService
{
    /**
     * @return array{status: string, match_count?: int}
     */
    public function status(Lead $lead): array
    {
        $payload = [
            'status' => $lead->status->value,
        ];

        if ($lead->status !== LeadStatus::Processing) {
            $payload['match_count'] = $lead->leadMatches()
                ->where('is_visible_to_consumer', true)
                ->count();
        }

        return $payload;
    }

    /**
     * @return array{diagnosis: array<string, string>, matches: list<array<string, mixed>>, advisor: array<string, string>}
     */
    public function results(Lead $lead): array
    {
        $matches = $lead->leadMatches()
            ->with('company')
            ->where('is_visible_to_consumer', true)
            ->orderBy('rank')
            ->get();

        return [
            'diagnosis' => $this->diagnosis($lead),
            'matches' => $matches->map(fn (LeadMatch $m) => $this->formatConsumerMatch($m))->values()->all(),
            'advisor' => [
                'name' => 'Marco',
                'role' => 'Consulente pari',
                'story' => 'Ho affrontato la stessa scelta per mia madre. Posso aiutarti a capire le opzioni.',
                'cta_label' => 'Prenota una chiamata gratuita',
            ],
        ];
    }

    /**
     * @return Collection<int, LeadMatch>
     */
    public function matches(Lead $lead): Collection
    {
        return $lead->leadMatches()
            ->with('company')
            ->where('is_visible_to_consumer', true)
            ->orderBy('rank')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    private function diagnosis(Lead $lead): array
    {
        $autonomy = $lead->payload['autonomy'] ?? 'parziale';

        return match ($autonomy) {
            'non-autosufficiente' => [
                'recommendation' => 'Struttura residenziale o assistenza h24',
                'primary' => 'RSA',
                'secondary' => 'Assistenza domiciliare intensiva',
                'summary' => 'Per persone con autonomia molto ridotta è consigliata una struttura con personale notturno.',
            ],
            'parziale' => [
                'recommendation' => 'Assistenza domiciliare con supporto strutturato',
                'primary' => 'Assistenza domiciliare',
                'secondary' => 'Centro diurno / RSA leggera',
                'summary' => 'Un mix di supporto a domicilio e servizi diurni può essere la soluzione più equilibrata.',
            ],
            default => [
                'recommendation' => 'Servizi di supporto leggero',
                'primary' => 'Assistenza domiciliare',
                'secondary' => 'Centro diurno',
                'summary' => 'Per persone autosufficienti bastano servizi flessibili a domicilio.',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConsumerMatch(LeadMatch $match): array
    {
        $company = $match->company;
        $attrs = $company?->dynamic_attributes ?? [];

        return [
            'id' => (string) $match->id,
            'company_id' => $company?->id,
            'name' => $company?->organization_name ?? 'Partner',
            'type' => $this->sectorLabel($attrs['sector'] ?? 'adi'),
            'location' => $company?->city ?? '',
            'compatibility' => $match->match_score,
            'image_url' => null,
            'description' => $attrs['notes'] ?? 'Struttura verificata Wenando.',
            'pros' => ['Vetting completato', 'Trust score elevato'],
            'contact_hint' => 'Registrati per salvare e contattare',
        ];
    }

    private function sectorLabel(string $sector): string
    {
        return match ($sector) {
            'rsa' => 'Residenza Sanitaria Assistenziale',
            'adi' => 'Assistenza Domiciliare',
            'centro' => 'Centro diurno',
            'clinica' => 'Clinica / ambulatorio',
            default => 'Assistenza Senior Care',
        };
    }
}
