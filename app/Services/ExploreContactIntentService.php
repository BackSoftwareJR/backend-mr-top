<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Http\Requests\V1\B2C\ContactIntentRequest;
use App\Http\Resources\V1\MatchResultResource;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExploreContactIntentService
{
    public function __construct(
        private readonly ConsentLogService $consentLogService,
        private readonly LeadInterestAreaService $interestAreaService,
        private readonly LeadMatchingService $matchingService,
    ) {}

    /**
     * @return array{lead: Lead, matches: list<array<string, mixed>>}
     */
    public function submit(ContactIntentRequest $request, ?User $user = null): array
    {
        return DB::transaction(function () use ($request, $user): array {
            $validated = $request->validated();
            $selections = $validated['selections'] ?? [];
            $refinementHistory = $validated['refinementHistory'] ?? [];
            $query = trim($validated['query']);
            $sectorSlug = $validated['sector_slug'] ?? 'senior-care';
            $sector = Sector::query()->where('slug', $sectorSlug)->firstOrFail();

            $location = $this->resolveLocation($selections, $refinementHistory, $query);
            $autonomy = $this->resolveAutonomy($selections);
            $budget = $this->resolveBudget($selections);
            $contact = $validated['contact'];
            $contactEmail = $this->normalizeContactEmail($contact['email'] ?? null);
            $needSummary = $this->buildNeedSummary($autonomy);

            $payload = [
                'autonomy' => $autonomy,
                'location' => $location,
                'budget' => $budget,
                'contact' => array_filter([
                    'nome' => $contact['nome'],
                    'telefono' => $contact['telefono'],
                    'email' => $contactEmail,
                ], fn ($value) => $value !== null),
                'source' => 'explore_contact_intent',
                'explore' => [
                    'query' => $query,
                    'active_path_id' => $validated['activePathId'] ?? null,
                    'selections' => $selections,
                    'explore_session_id' => $validated['explore_session_id'] ?? null,
                ],
            ];

            $interestAreas = is_array($validated['interest_areas'] ?? null)
                ? $validated['interest_areas']
                : [];

            $lead = Lead::query()->create([
                'sector_id' => $sector->id,
                'user_id' => $user?->id,
                'status' => LeadStatus::Processing,
                'payload' => $payload,
                'contact_name' => $contact['nome'],
                'contact_phone' => $contact['telefono'],
                'contact_email' => $contactEmail,
                'location_label' => $location['label'],
                'budget_min' => $budget['min'],
                'budget_max' => $budget['max'],
                'need_summary' => $needSummary,
                'title' => $this->buildSearchTitle($sector->name, $needSummary, $location['label']),
            ]);

            $lead->update([
                'public_ref' => sprintf('LD-%d', $lead->id),
            ]);

            $this->interestAreaService->syncFromPayload(
                $lead,
                $interestAreas,
                $location['label'],
            );

            $consentValidated = array_merge($validated, [
                'sector_slug' => $sectorSlug,
                'payload' => $payload,
            ]);

            $this->consentLogService->recordLeadSubmissionConsents(
                $lead,
                $request,
                $user,
                $consentValidated,
            );

            $matches = $this->matchingService->matchLead($lead->fresh(['interestAreas']));

            $visible = collect($matches)
                ->filter(fn ($match) => $match->is_visible_to_consumer)
                ->sortBy('rank')
                ->values();

            foreach ($visible as $match) {
                $match->load(['company.profile']);
            }

            return [
                'lead' => $lead->fresh(),
                'matches' => MatchResultResource::collection($visible)->resolve(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $selections
     * @param  list<array<string, mixed>>  $refinementHistory
     * @return array{label: string, value: string}
     */
    private function resolveLocation(array $selections, array $refinementHistory, string $query): array
    {
        $zoneId = isset($selections['refinement_zone'])
            ? (string) $selections['refinement_zone']
            : null;
        $label = null;

        foreach ($refinementHistory as $step) {
            if (! is_array($step)) {
                continue;
            }

            if (($step['questionId'] ?? '') === 'refinement_zone') {
                $label = isset($step['answerLabel']) ? trim((string) $step['answerLabel']) : null;

                break;
            }
        }

        if ($label === null || $label === '') {
            $label = match ($zoneId) {
                'milano' => 'Milano e hinterland',
                'lombardia' => 'Altra zona in Lombardia',
                'altra' => 'Altra città o regione',
                default => null,
            };
        }

        if ($label === null || $label === '') {
            $label = $this->locationHintFromQuery($query) ?? 'Zona da definire';
        }

        $value = $this->slugifyLocationValue($zoneId, $label);

        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    /**
     * @param  array<string, mixed>  $selections
     */
    private function resolveAutonomy(array $selections): string
    {
        $care = $selections['refinement_care'] ?? null;

        return match ($care) {
            'partial' => 'autosufficiente',
            'moderate' => 'parziale',
            'intensive' => 'non-autosufficiente',
            default => 'parziale',
        };
    }

    /**
     * @param  array<string, mixed>  $selections
     * @return array{min: int, max: int}
     */
    private function resolveBudget(array $selections): array
    {
        return match ($selections['refinement_budget'] ?? 'mid') {
            'under1500' => ['min' => 500, 'max' => 1500],
            'high' => ['min' => 2500, 'max' => 5000],
            default => ['min' => 1500, 'max' => 2500],
        };
    }

    private function slugifyLocationValue(?string $zoneId, string $label): string
    {
        if ($zoneId !== null && preg_match('/^[a-z0-9-]+$/', $zoneId) === 1) {
            return $zoneId;
        }

        $slug = Str::slug($label, '-');

        if ($slug === '') {
            return 'zona-ricerca';
        }

        return strlen($slug) > 64 ? substr($slug, 0, 64) : $slug;
    }

    private function locationHintFromQuery(string $query): ?string
    {
        $normalized = mb_strtolower(trim($query));
        $hints = [
            'milano' => 'Milano',
            'monza' => 'Monza',
            'bergamo' => 'Bergamo',
            'brescia' => 'Brescia',
            'lombardia' => 'Lombardia',
            'torino' => 'Torino',
            'piemonte' => 'Piemonte',
            'roma' => 'Roma',
            'lazio' => 'Lazio',
        ];

        foreach ($hints as $hint => $label) {
            if (str_contains($normalized, $hint)) {
                return $label;
            }
        }

        return null;
    }

    private function normalizeContactEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $trimmed = trim($email);

        return $trimmed === '' ? null : Str::lower($trimmed);
    }

    private function buildNeedSummary(string $autonomy): string
    {
        return match ($autonomy) {
            'autosufficiente' => 'Assistenza per persona autosufficiente',
            'parziale' => 'Assistenza per autonomia parziale',
            'non-autosufficiente' => 'Assistenza per persona non autosufficiente',
            default => 'Richiesta assistenza senior care',
        };
    }

    private function buildSearchTitle(string $sectorName, string $needSummary, string $locationLabel): string
    {
        $title = sprintf('%s · %s · %s', $sectorName, $needSummary, $locationLabel);

        return mb_strlen($title) > 255 ? mb_substr($title, 0, 252).'…' : $title;
    }
}
