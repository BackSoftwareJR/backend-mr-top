<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use App\Models\EditorialRubric;
use App\Models\Sector;
use App\Services\Editorial\EditorialSearchIndexer;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NandoEditorialContextTest extends TestCase
{
    use RefreshDatabase;

    private const CONTEXT_ENDPOINT = '/api/v1/b2c/nando/editorial-context';

    private const REFINE_ENDPOINT = '/api/v1/b2c/nando/refine';

    private Sector $sector;

    private EditorialRubric $guideRubric;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('nando-refine');

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_editorial_context_returns_snippets_for_matching_published_content(): void
    {
        $content = $this->indexContent([
            'title' => 'Badante convivente: guida pratica',
            'slug' => 'badante-convivente-guida',
            'excerpt' => 'Tutto quello che serve sapere sulla badante convivente.',
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Informazioni utili sulla badante convivente.</p>']],
            ],
        ]);

        $response = $this->getJson(self::CONTEXT_ENDPOINT.'?q=badante&limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'snippets' => [
                        ['title', 'excerpt', 'url', 'rubric', 'type', 'relevance_score'],
                    ],
                ],
            ]);

        $snippets = $response->json('data.snippets');
        $this->assertIsArray($snippets);
        $this->assertNotEmpty($snippets);
        $this->assertStringContainsString('badante', mb_strtolower($snippets[0]['title']));
        $this->assertSame('/magazine/guide/badante-convivente-guida', $snippets[0]['url']);
        $this->assertSame('guide', $snippets[0]['rubric']);
        $this->assertSame('article', $snippets[0]['type']);
        $this->assertIsFloat($snippets[0]['relevance_score']);
        $this->assertSame($content->title, $snippets[0]['title']);
    }

    public function test_editorial_context_returns_empty_snippets_for_no_matches(): void
    {
        $this->indexContent([
            'title' => 'RSA Milano: come scegliere',
            'slug' => 'rsa-milano-come-scegliere',
        ]);

        $response = $this->getJson(self::CONTEXT_ENDPOINT.'?q=centro+diurno+bergamo');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.snippets', []);
    }

    public function test_editorial_context_requires_query(): void
    {
        $this->getJson(self::CONTEXT_ENDPOINT)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_refine_includes_editorial_context_when_content_exists(): void
    {
        config(['services.groq.api_key' => 'test-groq-key']);

        $this->indexContent([
            'title' => 'Badante a Milano: costi e consigli',
            'slug' => 'badante-milano-costi',
            'excerpt' => 'Guida ai costi della badante a Milano.',
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Assistenza domiciliare a Milano.</p>']],
            ],
        ]);

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

        $response = $this->postJson(self::REFINE_ENDPOINT, [
            'query' => 'badante milano',
            'selections' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.source', 'groq');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'api.groq.com/openai/v1/chat/completions')) {
                return false;
            }

            $body = $request->data();
            $userMessage = collect($body['messages'] ?? [])
                ->firstWhere('role', 'user');

            if (! is_array($userMessage) || ! isset($userMessage['content'])) {
                return false;
            }

            $payload = json_decode((string) $userMessage['content'], true);

            if (! is_array($payload) || ! isset($payload['editorial_context'])) {
                return false;
            }

            $snippets = $payload['editorial_context'];
            if (! is_array($snippets) || $snippets === []) {
                return false;
            }

            $first = $snippets[0];

            return str_contains(mb_strtolower((string) ($first['title'] ?? '')), 'badante')
                && ($first['url'] ?? '') === '/magazine/guide/badante-milano-costi';
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function indexContent(array $overrides = []): EditorialContent
    {
        $content = EditorialContent::factory()->published()->create(array_merge([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'content_type' => EditorialContentType::Article,
            'noindex' => false,
        ], $overrides));

        app(EditorialSearchIndexer::class)->index($content);

        return $content;
    }
}
