<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Models\AdvisorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2c_advisor_endpoint_returns_default_profile(): void
    {
        AdvisorProfile::query()->create([
            'slug' => 'marco',
            'name' => 'Marco',
            'title' => 'Consulente pari',
            'bio' => 'Parla con Marco. Ha affrontato la stessa situazione con suo padre 2 anni fa.',
            'cta_label' => 'Prenota una chiamata gratuita (15 min)',
            'avatar_url' => 'https://cdn.example.test/marco.jpg',
            'calendly_url' => 'https://calendly.com/marco/15min',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/b2c/advisor')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Marco')
            ->assertJsonPath('data.role', 'Consulente pari')
            ->assertJsonPath('data.story', 'Parla con Marco. Ha affrontato la stessa situazione con suo padre 2 anni fa.')
            ->assertJsonPath('data.cta_label', 'Prenota una chiamata gratuita (15 min)')
            ->assertJsonPath('data.avatar_url', 'https://cdn.example.test/marco.jpg')
            ->assertJsonPath('data.calendly_url', 'https://calendly.com/marco/15min');
    }

    public function test_b2c_advisor_endpoint_falls_back_when_no_rows(): void
    {
        $this->getJson('/api/v1/b2c/advisor')
            ->assertOk()
            ->assertJsonPath('data.name', 'Marco')
            ->assertJsonPath('data.role', 'Consulente pari')
            ->assertJsonMissingPath('data.avatar_url');
    }
}
