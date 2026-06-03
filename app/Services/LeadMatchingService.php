<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadMatchingService
{
    /**
     * @return list<LeadMatch>
     */
    public function matchLead(Lead $lead, bool $preserveManualLock = true): array
    {
        return DB::transaction(function () use ($lead, $preserveManualLock): array {
            $lead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
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
                ->with('latestTrustScore')
                ->where('sector_id', $lead->sector_id)
                ->where('vetting_status', VettingStatus::Approved)
                ->when($manualIds !== [], fn ($q) => $q->whereNotIn('id', $manualIds))
                ->get();

            $scored = [];
            foreach ($companies as $company) {
                $score = $this->scoreCompany($lead, $company, $rules);
                if ($score <= 0) {
                    continue;
                }
                $scored[] = ['company' => $company, 'score' => $score];
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
                    'metadata' => [
                        'ai_match_label' => sprintf('%s (%d%%)', $company->organization_name, $score),
                    ],
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
    private function scoreCompany(Lead $lead, Company $company, array $rules): int
    {
        $weights = $rules['weights'] ?? [
            'budget_overlap' => 0.25,
            'geo_match' => 0.20,
            'autonomy_fit' => 0.25,
            'trust_score' => 0.15,
            'capacity' => 0.10,
            'operational_bonus' => 0.05,
        ];

        $geo = $this->geoScore($lead, $company);
        if ($geo <= 0) {
            return 0;
        }

        $factors = [
            'budget_overlap' => $this->budgetScore($lead),
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

    private function budgetScore(Lead $lead): int
    {
        if ($lead->budget_min === null || $lead->budget_max === null) {
            return 70;
        }

        $width = max(1, $lead->budget_max - $lead->budget_min);

        return (int) min(100, round(($width / max($lead->budget_max, 1)) * 100));
    }

    private function geoScore(Lead $lead, Company $company): int
    {
        $leadCity = $this->extractCity($lead->location_label ?? '');
        $companyCity = Str::lower($company->city ?? '');

        if ($leadCity === '' || $companyCity === '') {
            return 50;
        }

        if (str_contains($leadCity, $companyCity) || str_contains($companyCity, $leadCity)) {
            return 100;
        }

        $leadProvince = $this->extractProvince($lead->location_label ?? '');
        if ($leadProvince !== '' && str_contains($lead->location_label ?? '', $company->city ?? '')) {
            return 80;
        }

        return 30;
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

    private function extractCity(string $label): string
    {
        $parts = explode(',', $label);

        return strtolower(trim($parts[0] ?? $label));
    }

    private function extractProvince(string $label): string
    {
        if (preg_match('/\(([A-Z]{2})\)/', $label, $m)) {
            return strtolower($m[1]);
        }

        return '';
    }
}
