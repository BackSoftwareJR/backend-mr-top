<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Sector;
use App\Support\ItalianLocationParser;
use App\Support\SpatialMatcher;
use Illuminate\Support\Facades\DB;

class LeadMatchingService
{
    public function __construct(
        private readonly ItalianLocationParser $locationParser = new ItalianLocationParser,
        private readonly SpatialMatcher $spatialMatcher = new SpatialMatcher,
    ) {}

    /**
     * @return list<LeadMatch>
     */
    public function matchLead(Lead $lead, bool $preserveManualLock = true): array
    {
        return DB::transaction(function () use ($lead, $preserveManualLock): array {
            $lead = Lead::query()
                ->with('interestAreas')
                ->whereKey($lead->id)
                ->lockForUpdate()
                ->firstOrFail();
            $sector = Sector::query()->findOrFail($lead->sector_id);
            $rules = $sector->matching_rules ?? [];
            $defaultUnlock = (int) ($rules['default_unlock_cost'] ?? 15);
            $minMarketplace = (int) ($rules['min_match_score_marketplace'] ?? 80);
            $minB2c = (int) ($rules['b2c_visible_min_score'] ?? 70);
            $maxB2c = (int) ($rules['max_b2c_results'] ?? 3);

            $manualIds = [];
            if ($preserveManualLock) {
                $manualIds = LeadMatch::query()
                    ->where('lead_id', $lead->id)
                    ->where('metadata->manual_lock', true)
                    ->pluck('company_id')
                    ->all();
            }

            LeadMatch::query()
                ->where('lead_id', $lead->id)
                ->when($manualIds !== [], fn ($q) => $q->whereNotIn('company_id', $manualIds))
                ->delete();

            $companies = Company::query()
                ->with(['latestTrustScore', 'coverageZone'])
                ->where('sector_id', $lead->sector_id)
                ->where('vetting_status', VettingStatus::Approved)
                ->when($manualIds !== [], fn ($q) => $q->whereNotIn('id', $manualIds))
                ->get();

            $scored = [];
            foreach ($companies as $company) {
                $geoResult = $this->geoScore($lead, $company);
                if ($geoResult['score'] <= 0) {
                    continue;
                }

                $score = $this->scoreCompany($lead, $company, $rules, $geoResult['score']);
                if ($score <= 0) {
                    continue;
                }

                $scored[] = [
                    'company' => $company,
                    'score' => $score,
                    'spatial_match' => $geoResult['spatial_match'],
                    'distance_km' => $geoResult['distance_km'],
                ];
            }

            usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            $matches = [];
            $rank = 1;
            foreach ($scored as $item) {
                $score = $item['score'];
                $company = $item['company'];
                $match = LeadMatch::query()->create([
                    'lead_id' => $lead->id,
                    'company_id' => $company->id,
                    'match_score' => $score,
                    'rank' => $rank,
                    'is_visible_to_consumer' => $score >= $minB2c && $rank <= $maxB2c,
                    'is_in_marketplace' => $score >= $minMarketplace,
                    'unlock_cost_credits' => $defaultUnlock,
                    'metadata' => array_filter([
                        'ai_match_label' => sprintf('%s (%d%%)', $company->organization_name, $score),
                        'spatial_match' => $item['spatial_match'],
                        'distance_km' => $item['distance_km'],
                    ], fn ($value) => $value !== null),
                ]);
                $matches[] = $match;
                $rank++;
            }

            $lead->update(['status' => LeadStatus::Routed]);

            return $matches;
        });
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function scoreCompany(Lead $lead, Company $company, array $rules, int $geo): int
    {
        $weights = $rules['weights'] ?? [
            'budget_overlap' => 0.25,
            'geo_match' => 0.20,
            'autonomy_fit' => 0.25,
            'trust_score' => 0.15,
            'capacity' => 0.10,
            'operational_bonus' => 0.05,
        ];

        $factors = [
            'budget_overlap' => $this->budgetScore($lead, $company),
            'geo_match' => $geo,
            'autonomy_fit' => $this->autonomyScore($lead, $company),
            'trust_score' => (int) ($company->latestTrustScore?->score ?? 70),
            'capacity' => $this->capacityScore($company),
            'operational_bonus' => $this->operationalBonus($lead, $company),
        ];

        $total = 0.0;
        foreach ($weights as $key => $weight) {
            $total += ((float) $weight) * ($factors[$key] ?? 0);
        }

        return (int) round(min(100, max(0, $total)));
    }

    /**
     * @return array{score: int, spatial_match: bool, distance_km: float|null}
     */
    private function geoScore(Lead $lead, Company $company): array
    {
        $coverage = $company->coverageZone;
        $interestAreas = $lead->interestAreas;

        if ($coverage !== null && $interestAreas->isNotEmpty()) {
            $bestDistance = null;

            foreach ($interestAreas as $interest) {
                if (! $this->spatialMatcher->coverageOverlapsInterestArea($coverage, $interest)) {
                    continue;
                }

                $distance = $this->spatialMatcher->haversineDistanceKm(
                    (float) $coverage->center_lat,
                    (float) $coverage->center_lng,
                    (float) $interest->center_lat,
                    (float) $interest->center_lng,
                );

                $bestDistance = $bestDistance === null
                    ? $distance
                    : min($bestDistance, $distance);

                return [
                    'score' => 100,
                    'spatial_match' => true,
                    'distance_km' => round($bestDistance, 1),
                ];
            }

            return ['score' => 0, 'spatial_match' => false, 'distance_km' => null];
        }

        $textScore = $this->locationParser->bestGeoScore(
            $lead->location_label,
            $this->companyLocationLabels($company),
        );

        return ['score' => $textScore, 'spatial_match' => false, 'distance_km' => null];
    }

    private function budgetScore(Lead $lead, Company $company): int
    {
        if ($lead->budget_min === null || $lead->budget_max === null) {
            return 70;
        }

        $companyRange = $this->companyAcceptableBudgetRange($company);
        if ($companyRange === null) {
            return 70;
        }

        [$companyMin, $companyMax] = $companyRange;
        $leadMin = $lead->budget_min;
        $leadMax = $lead->budget_max;
        $leadWidth = max(1, $leadMax - $leadMin);

        $overlapMin = max($leadMin, $companyMin);
        $overlapMax = min($leadMax, $companyMax);
        $overlapWidth = max(0, $overlapMax - $overlapMin);

        return (int) min(100, max(0, round(($overlapWidth / $leadWidth) * 100)));
    }

    /**
     * Acceptable monthly budget (EUR) from company.dynamic_attributes.
     *
     * @return array{0: int, 1: int}|null [min, max] when both bounds are present
     */
    private function companyAcceptableBudgetRange(Company $company): ?array
    {
        $attrs = $company->dynamic_attributes ?? [];

        $min = $attrs['budget_min'] ?? $attrs['pricing_min'] ?? null;
        $max = $attrs['budget_max'] ?? $attrs['pricing_max'] ?? null;

        if (isset($attrs['pricing']) && is_array($attrs['pricing'])) {
            $min ??= $attrs['pricing']['min'] ?? null;
            $max ??= $attrs['pricing']['max'] ?? null;
        }

        if (! is_numeric($min) || ! is_numeric($max)) {
            return null;
        }

        $min = (int) $min;
        $max = (int) $max;

        if ($min > $max) {
            return null;
        }

        return [$min, $max];
    }

    /**
     * @return list<string>
     */
    private function companyLocationLabels(Company $company): array
    {
        $labels = [];

        if ($company->city !== null && trim($company->city) !== '') {
            $labels[] = $company->city;
        }

        $serviceAreas = $company->dynamic_attributes['service_areas'] ?? null;
        if (is_array($serviceAreas)) {
            foreach ($serviceAreas as $area) {
                if (is_string($area) && trim($area) !== '') {
                    $labels[] = $area;
                }
            }
        }

        return $labels;
    }

    private function autonomyScore(Lead $lead, Company $company): int
    {
        $autonomy = $lead->payload['autonomy'] ?? 'parziale';
        $attrs = $company->dynamic_attributes ?? [];
        $nonSelf = (bool) ($attrs['nonSelfSufficient'] ?? false);
        $nightStaff = (bool) ($attrs['nightStaff'] ?? false);

        return match ($autonomy) {
            'non-autosufficiente' => ($nonSelf && $nightStaff) ? 100 : ($nonSelf ? 60 : 20),
            'parziale' => 85,
            'autosufficiente' => match ($attrs['sector'] ?? 'adi') {
                'rsa' => 60,
                default => 90,
            },
            default => 70,
        };
    }

    private function capacityScore(Company $company): int
    {
        $capacity = (int) ($company->dynamic_attributes['capacity'] ?? 10);

        return (int) min(100, $capacity * 5);
    }

    private function operationalBonus(Lead $lead, Company $company): int
    {
        $bonus = 0;
        $attrs = $company->dynamic_attributes ?? [];

        if (($attrs['nightStaff'] ?? false) && str_contains(strtolower($lead->need_summary ?? ''), 'h24')) {
            $bonus += 50;
        }

        $schedule = $company->schedule ?? [];
        foreach ($schedule as $day) {
            if (is_array($day) && ($day['open'] ?? false) && ($day['slots'] ?? '') !== '') {
                $bonus += 10;
                break;
            }
        }

        return min(100, $bonus);
    }
}
