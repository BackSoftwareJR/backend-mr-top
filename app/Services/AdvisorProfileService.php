<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdvisorProfile;

class AdvisorProfileService
{
    /**
     * @return array<string, string>
     */
    public function defaultPayload(): array
    {
        $profile = AdvisorProfile::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('id')
            ->first();

        if ($profile === null) {
            $profile = AdvisorProfile::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        if ($profile !== null) {
            return $profile->toConsumerArray();
        }

        return $this->fallbackPayload();
    }

    /**
     * @return array<string, string>
     */
    private function fallbackPayload(): array
    {
        return [
            'name' => 'Marco',
            'role' => 'Consulente pari',
            'story' => 'Ho affrontato la stessa scelta per mia madre. Posso aiutarti a capire le opzioni.',
            'cta_label' => 'Prenota una chiamata gratuita',
        ];
    }
}
