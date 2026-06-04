<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSubmissionMissingSectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2c_lead_submission_rejects_unknown_sector_slug(): void
    {
        $response = $this->postJson('/api/v1/b2c/leads', [
            'sector_slug' => 'senior-care',
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                'budget' => ['min' => 1500, 'max' => 2500],
                'contact' => ['nome' => 'Mario', 'telefono' => '+39 333 123 4567'],
            ],
            'consent' => [
                'privacy_accepted' => true,
                'terms_accepted' => true,
                'lead_sharing_accepted' => true,
            ],
            'consent_text_hash' => '2215df58adb1f22c6ebabdd592c36ca5f58ab26c8c199d48fe617b0af61dcf00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.sector_slug.0', 'Settore non trovato.');
    }
}
