<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Http\Resources\V1\UserSearchResource;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\SavedMatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserAreaService
{
    /**
     * @return array{latest_search: array<string, mixed>|null, display_name: string}
     */
    public function home(User $user): array
    {
        $latest = $user->leads()->latest()->first();

        return [
            'latest_search' => $latest !== null
                ? (new UserSearchResource($latest))->resolve()
                : null,
            'display_name' => $user->name ?? $user->email,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Lead>
     */
    public function searches(User $user, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return $user->leads()
            ->withCount(['leadMatches as match_count' => fn ($q) => $q->where('is_visible_to_consumer', true)])
            ->latest()
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * @return array{search: array<string, mixed>, matches?: list<array<string, mixed>>}
     */
    public function searchDetail(User $user, Lead $lead): array
    {
        if ($lead->user_id !== $user->id) {
            abort(403, 'Ricerca non autorizzata.');
        }

        $results = app(B2cLeadResultsService::class);

        return [
            'search' => (new UserSearchResource($lead))->resolve(),
            'matches' => $lead->status !== LeadStatus::Processing
                ? $results->results($lead)['matches']
                : null,
        ];
    }

    /**
     * @return array{search: array<string, mixed>}
     */
    public function updateSearchTitle(Lead $lead, string $title): array
    {
        $lead->update(['title' => $title]);

        return [
            'search' => (new UserSearchResource($lead->fresh()))->resolve(),
        ];
    }

    /**
     * Link orphan wizard leads whose contact_email matches the authenticated consumer.
     */
    public function attachOrphanLeadsByEmail(User $user): User
    {
        $email = Str::lower(trim($user->email));

        $orphans = Lead::query()
            ->whereNull('user_id')
            ->whereNotNull('contact_email')
            ->whereRaw('LOWER(contact_email) = ?', [$email])
            ->orderBy('id')
            ->get();

        foreach ($orphans as $lead) {
            $lead->update(['user_id' => $user->id]);
            $user = $this->hydrateUserProfileFromLead($user, $lead->fresh());
        }

        return $user->fresh();
    }

    /**
     * @return array{lead: Lead, user: User}
     */
    public function attachLeadToUser(User $user, string $leadUuid): array
    {
        $lead = Lead::query()->where('uuid', $leadUuid)->firstOrFail();

        if ($lead->user_id === null) {
            $lead->update(['user_id' => $user->id]);
        } elseif ($lead->user_id !== $user->id) {
            abort(403, 'Lead non associabile.');
        }

        $lead = $lead->fresh();
        $user = $this->hydrateUserProfileFromLead($user, $lead);

        return ['lead' => $lead, 'user' => $user];
    }

    private function hydrateUserProfileFromLead(User $user, Lead $lead): User
    {
        $updates = [];

        if (($user->phone === null || $user->phone === '') && $lead->contact_phone) {
            $updates['phone'] = $lead->contact_phone;
        }

        $emailPrefix = Str::before($user->email, '@');
        if (
            $lead->contact_name
            && ($user->name === null || $user->name === '' || $user->name === $emailPrefix)
        ) {
            $updates['name'] = $lead->contact_name;
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return $user->fresh();
    }

    /**
     * @param  array{name?: string, phone?: string|null}  $attributes
     * @return array{user: User}
     */
    public function updateProfile(User $user, array $attributes): array
    {
        $allowed = array_intersect_key($attributes, array_flip(['name', 'phone']));

        if ($allowed !== []) {
            $user->update($allowed);
        }

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

            $this->assertSavableMatch($user, $companyId, $leadMatchId);

            $resolvedCompanyId = $companyId;
            if ($resolvedCompanyId === null && $leadMatchId !== null) {
                $resolvedCompanyId = LeadMatch::query()
                    ->whereKey($leadMatchId)
                    ->value('company_id');
            }

            SavedMatch::query()->create([
                'user_id' => $user->id,
                'company_id' => $resolvedCompanyId,
                'lead_match_id' => $leadMatchId,
            ]);

            return ['saved' => true];
        });
    }

    private function assertSavableMatch(User $user, ?int $companyId, ?int $leadMatchId): void
    {
        $visibleMatches = LeadMatch::query()
            ->where('is_visible_to_consumer', true)
            ->whereHas('lead', fn ($leadQuery) => $leadQuery->where('user_id', $user->id));

        if ($leadMatchId !== null) {
            if (! $visibleMatches->clone()->whereKey($leadMatchId)->exists()) {
                abort(403, 'Match non salvabile.');
            }

            return;
        }

        if ($companyId !== null && ! $visibleMatches->clone()->where('company_id', $companyId)->exists()) {
            abort(403, 'Match non salvabile.');
        }
    }
}
