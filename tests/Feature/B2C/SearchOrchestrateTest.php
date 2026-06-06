<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SearchOrchestrateTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/b2c/search/orchestrate';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('search-orchestrate');
        config([
            'services.groq.api_key' => null,
            'services.groq.model' => 'llama-3.3-70b-versatile',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
        ]);
    }

    public function test_orchestrate_requires_query(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['query']]]);
    }

    public function test_orchestrate_falls_back_when_groq_is_not_configured(): void
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
            ->assertJsonCount(3, 'data.paths')
            ->assertJsonPath('data.paths.0.rank', 1)
            ->assertJsonPath('data.nando.question.id', 'refinement_zone')
            ->assertJsonStructure([
                'data' => [
                    'paths' => [
                        ['id', 'type', 'rank', 'label', 'title', 'subtitle', 'summary', 'whyRecommended', 'imageHint'],
                    ],
                    'editorial',
                    'nando' => ['microPrompt', 'actions', 'question'],
                ],
            ]);
    }

    public function test_orchestrate_uses_groq_when_configured_and_response_is_valid(): void
    {
        config(['services.groq.api_key' => 'test-groq-key']);

        $groqPayload = [
            'pageTitle' => 'Percorsi per badante a Milano',
            'supported' => true,
            'paths' => [
                [
                    'id' => 'path_home_care',
                    'type' => 'service',
                    'rank' => 1,
                    'label' => 'Scelta consigliata',
                    'title' => 'Assistenza domiciliare',
                    'subtitle' => 'Percorso · Casa',
                    'summary' => 'Supporto a domicilio con agenzia certificata.',
                    'whyRecommended' => 'Mantiene la familiarità dell’ambiente domestico.',
                    'refinementNeeded' => ['budget'],
                    'imageHint' => 'home_care',
                ],
                [
                    'id' => 'path_rsa',
                    'type' => 'structure',
                    'rank' => 2,
                    'label' => 'Alternativa valida',
                    'title' => 'RSA con assistenza media',
                    'subtitle' => 'Percorso · Struttura',
                    'summary' => 'Assistenza continuativa in struttura.',
                    'whyRecommended' => 'Utile se serve presenza h24.',
                    'refinementNeeded' => ['budget'],
                    'imageHint' => 'rsa',
                ],
                [
                    'id' => 'path_day',
                    'type' => 'service',
                    'rank' => 3,
                    'label' => 'Da valutare',
                    'title' => 'Centro diurno',
                    'subtitle' => 'Percorso · Equilibrio',
                    'summary' => 'Assistenza di giorno, rientro a casa la sera.',
                    'whyRecommended' => 'Soluzione intermedia per supporto nelle ore centrali.',
                    'refinementNeeded' => ['budget'],
                    'imageHint' => 'community',
                ],
            ],
            'editorial' => [
                [
                    'id' => 'editorial-1',
                    'title' => 'RSA vs assistenza domiciliare',
                    'summary' => 'Confronto tra costi e qualità della vita.',
                    'url' => '#',
                    'relevanceReason' => 'Aiuta a scegliere il percorso.',
                ],
            ],
            'nando' => [
                'microPrompt' => 'Affina la ricerca',
                'actions' => [
                    ['id' => 'explain_why', 'label' => 'Scopri perché ti abbiamo consigliato'],
                ],
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
            ],
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
            ->assertJsonPath('data.pageTitle', 'Percorsi per badante a Milano')
            ->assertJsonPath('data.paths.0.title', 'Assistenza domiciliare')
            ->assertJsonPath('data.nando.question.id', 'refinement_budget');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.groq.com/openai/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-groq-key');
        });
    }
}
