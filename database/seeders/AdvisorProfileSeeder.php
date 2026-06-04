<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdvisorProfile;
use Illuminate\Database\Seeder;

class AdvisorProfileSeeder extends Seeder
{
    public function run(): void
    {
        AdvisorProfile::query()->updateOrCreate(
            ['slug' => 'marco'],
            [
                'name' => 'Marco',
                'title' => 'Consulente pari',
                'bio' => 'Parla con Marco. Ha affrontato la stessa situazione con suo padre 2 anni fa. Nessuna vendita, solo l\'esperienza di chi ci è già passato.',
                'cta_label' => 'Prenota una chiamata gratuita (15 min)',
                'avatar_url' => null,
                'calendly_url' => null,
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }
}
