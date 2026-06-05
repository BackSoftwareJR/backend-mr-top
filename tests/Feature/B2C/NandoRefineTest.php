<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NandoRefineTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/b2c/nando/refine';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('nando-refine');
        config([
            'services.groq.api_key' => null,
            'services.groq.model' => 'llama-3.3-70b-versatile',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
        ]);
    }

    public function test_refine_requires_query(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['query']]]);
    }

    public function test_refine_falls_back_when_groq_is_not_configured(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'query' => 'badante per mamma',
            'selections' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.source', 'fallback')
            ->assertJsonPath('data.pageTitle', 'Risultati per badante per mamma')
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.question.id', 'refinement_zone')
            ->assertJsonPath('data.complete', false);
    }

    public function test_refine_uses_groq_when_configured_and_response_is_valid(): void
    {
        config(['services.groq.api_key' => 'test-groq-key']);

        $groqPayload = [
            'pageTitle' => 'Badante a Milano per assistenza domiciliare',
            'supported' => true,
            'question' => [
                'id' => 'refinement_budget',
                'question' => 'Quale budget mensile avete in mente?',
                'hint' => 'Solo orientativo.',
                'options' => [
                    ['id' => 'under1500', 'label' => 'Fino a 1.500 €'],
                    ['id' => 'mid', 'label' => '1.500 – 2.500 €'],
                    ['id' => 'high', 'label' => 'Oltre 2.500 €'],
                ],
            ],
            'complete' => false,
        ];

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode($groqPayload, JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $response = $this->postJson(self::ENDPOINT, [
            'query' => 'badante milano',
            'selections' => ['refinement_zone' => 'milano'],
            'customNotes' => 'Serve aiuto di giorno',
            'refinementHistory' => [
                ['questionId' => 'refinement_zone', 'answerLabel' => 'Milano e hinterland'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.source', 'groq')
            ->assertJsonPath('data.pageTitle', 'Badante a Milano per assistenza domiciliare')
            ->assertJsonPath('data.question.id', 'refinement_budget')
            ->assertJsonPath('data.complete', false);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.groq.com/openai/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-groq-key');
        });
    }
}
