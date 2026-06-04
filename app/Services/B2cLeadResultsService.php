<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Http\Resources\V1\MatchResultResource;
use App\Models\Lead;
use App\Models\LeadMatch;
use Illuminate\Database\Eloquent\Collection;

class B2cLeadResultsService
{
    public function __construct(
        private readonly AdvisorProfileService $advisorProfiles,
    ) {}

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
        $matches = $this->consumerMatches($lead);

        return [
            'diagnosis' => $this->diagnosis($lead),
            'matches' => MatchResultResource::collection($matches)->resolve(),
            'advisor' => $this->advisorProfiles->defaultPayload(),
        ];
    }

    /**
     * @return Collection<int, LeadMatch>
     */
    public function matches(Lead $lead): Collection
    {
        return $this->consumerMatches($lead);
    }

    /**
     * @return Collection<int, LeadMatch>
     */
    private function consumerMatches(Lead $lead): Collection
    {
        return $lead->leadMatches()
            ->with(['company.profile'])
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
}
