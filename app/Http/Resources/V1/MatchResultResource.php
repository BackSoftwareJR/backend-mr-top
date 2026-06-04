<?php

namespace App\Http\Resources\V1;

use App\Models\LeadMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * B2C consumer match card (flat shape aligned with mockMatches.js).
 *
 * API contract:
 * - id: lead_match id
 * - company_id: internal company id (saved-matches pivot)
 * - name, type, location, compatibility, image_url, description, pros, contact_hint
 * - tagline: optional short hook from company_profiles
 *
 * Profile fields come from company_profiles when present; falls back to company ops data.
 */
class MatchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeadMatch $match */
        $match = $this->resource;
        $company = $match->company;
        $profile = $company?->profile;
        $attrs = $company?->dynamic_attributes ?? [];
        $sectorKey = is_string($attrs['sector'] ?? null) ? $attrs['sector'] : 'adi';

        return [
            'id' => (string) $match->id,
            'company_id' => $company?->id,
            'name' => $profile?->display_name ?? $company?->organization_name ?? 'Partner',
            'type' => $profile?->service_type ?? $this->sectorLabel($sectorKey),
            'location' => $profile?->location_label ?? $company?->city ?? '',
            'compatibility' => $match->match_score,
            'image_url' => $profile?->image_url,
            'tagline' => $profile?->tagline,
            'description' => $profile?->description ?? $attrs['notes'] ?? 'Struttura verificata Wenando.',
            'pros' => $profile?->pros ?? ['Vetting completato', 'Trust score elevato'],
            'contact_hint' => $profile?->contact_hint ?? 'Registrati per salvare e contattare',
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
