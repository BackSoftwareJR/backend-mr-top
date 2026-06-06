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
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EditorialSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/b2c/search/editorial';

    private Sector $sector;

    private EditorialRubric $guideRubric;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('search-editorial');

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_editorial_returns_items_envelope(): void
    {
        $this->indexContent([
            'title' => 'Badante convivente: guida pratica',
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Informazioni utili sulla badante convivente.</p>']],
            ],
        ]);

        $response = $this->getJson(self::ENDPOINT.'?q=badante&limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['id', 'type', 'title', 'description', 'category', 'readMinutes', 'url'],
                    ],
                ],
            ]);

        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertLessThanOrEqual(5, count($items));
        $this->assertStringContainsString('badante', mb_strtolower($items[0]['title']));
    }

    public function test_editorial_filters_by_query_keywords(): void
    {
        $this->indexContent([
            'title' => 'RSA Milano: come scegliere',
            'slug' => 'rsa-milano-come-scegliere',
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Guida alle RSA di Milano.</p>']],
            ],
        ]);

        $response = $this->getJson(self::ENDPOINT.'?q=rsa milano');

        $response->assertOk();

        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        $combined = mb_strtolower(implode(' ', array_column($items, 'title')));
        $this->assertTrue(
            str_contains($combined, 'rsa') || str_contains($combined, 'milano'),
            'Expected RSA or Milano related editorial in top results.',
        );
    }

    public function test_editorial_respects_type_and_limit(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->indexContent([
                'title' => 'Guida assistenza '.$i,
                'slug' => 'guida-assistenza-'.$i,
                'content_type' => EditorialContentType::Article,
            ]);
        }

        $response = $this->getJson(self::ENDPOINT.'?type=article&limit=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data.items');

        foreach ($response->json('data.items') as $item) {
            $this->assertSame('article', $item['type']);
            $this->assertNotSame('#', $item['url']);
        }
    }

    public function test_editorial_returns_empty_items_for_unknown_type(): void
    {
        $this->indexContent(['title' => 'Guida generica']);

        $response = $this->getJson(self::ENDPOINT.'?type=unknown_type');

        $response->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_editorial_validates_limit(): void
    {
        $this->getJson(self::ENDPOINT.'?limit=99')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
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
