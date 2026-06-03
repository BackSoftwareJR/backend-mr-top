<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\SavedMatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserAreaService
{
    /**
     * @return array{latest_search: array<string, mixed>|null, display_name: string}
     */
    public function home(User $user): array
    {
        $latest = $user->leads()->latest()->first();

        return [
            'latest_search' => $latest !== null ? $this->formatSearch($latest) : null,
            'display_name' => $user->name ?? $user->email,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Lead>
     */
    public function searches(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->leads()
            ->withCount(['leadMatches as match_count' => fn ($q) => $q->where('is_visible_to_consumer', true)])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{search: array<string, mixed>, matches?: list<array<string, mixed>>}
     */
    public function searchDetail(User $user, int $leadId): array
    {
        $lead = $user->leads()->whereKey($leadId)->firstOrFail();
        $results = app(B2cLeadResultsService::class);

        return [
            'search' => $this->formatSearch($lead),
            'matches' => $lead->status !== LeadStatus::Processing
                ? $results->results($lead)['matches']
                : null,
        ];
    }

    public function attachLeadToUser(User $user, string $leadUuid): Lead
    {
        $lead = Lead::query()->where('uuid', $leadUuid)->firstOrFail();

        if ($lead->user_id === null) {
            $lead->update(['user_id' => $user->id]);
        } elseif ($lead->user_id !== $user->id) {
            abort(403, 'Lead non associabile.');
        }

        return $lead->fresh();
    }

    /**
     * @return array{user: User}
     */
    public function updateProfile(User $user, ?string $name, ?string $phone): array
    {
        $user->update(array_filter([
            'name' => $name,
            'phone' => $phone,
        ], fn ($v) => $v !== null));

        return ['user' => $user->fresh()];
    }

    /**
     * @return list<int>
     */
    public function savedMatchIds(User $user): array
    {
        return SavedMatch::query()
            ->where('user_id', $user->id)
            ->pluck('company_id')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{saved: bool}
     */
    public function toggleSavedMatch(User $user, ?int $companyId, ?int $leadMatchId): array
    {
        return DB::transaction(function () use ($user, $companyId, $leadMatchId): array {
            $query = SavedMatch::query()->where('user_id', $user->id);

            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            } elseif ($leadMatchId !== null) {
                $query->where('lead_match_id', $leadMatchId);
            }

            $existing = $query->first();

            if ($existing !== null) {
                $existing->delete();

                return ['saved' => false];
            }

            SavedMatch::query()->create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'lead_match_id' => $leadMatchId,
            ]);

            return ['saved' => true];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSearch(Lead $lead): array
    {
        $matchCount = $lead->leadMatches()
            ->where('is_visible_to_consumer', true)
            ->count();

        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'title' => $lead->need_summary ?? 'Ricerca assistenza',
            'location' => $lead->location_label,
            'date' => $lead->created_at?->toDateString(),
            'status' => $lead->status === LeadStatus::Processing ? 'processing' : 'completed',
            'match_count' => $matchCount,
            'answers' => $lead->payload,
        ];
    }
}
