<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use App\Models\EditorialMedia;
use App\Models\EditorialRubric;
use App\Models\Sector;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EditorialReadTest extends TestCase
{
    use RefreshDatabase;

    private const LIST_ENDPOINT = '/api/v1/b2c/editorial/contents';

    private const RUBRICS_ENDPOINT = '/api/v1/b2c/editorial/rubrics';

    private Sector $sector;

    private EditorialRubric $guideRubric;

    private EditorialRubric $storieRubric;

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
        $this->storieRubric = EditorialRubric::query()->where('slug', 'storie')->firstOrFail();
    }

    public function test_list_returns_only_published_contents(): void
    {
        $published = $this->createPublishedContent(['title' => 'Pubblicato visibile']);
        $this->createDraftContent(['title' => 'Bozza nascosta']);
        $this->createPublishedContent([
            'title' => 'Noindex nascosto',
            'noindex' => true,
        ]);

        $response = $this->getJson(self::LIST_ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.contents');

        $titles = array_column($response->json('data.contents'), 'title');
        $this->assertContains('Pubblicato visibile', $titles);
        $this->assertNotContains('Bozza nascosta', $titles);
        $this->assertNotContains('Noindex nascosto', $titles);

        $this->assertSame($published->uuid, $response->json('data.contents.0.id'));
    }

    public function test_draft_content_is_not_accessible_by_slug(): void
    {
        $draft = $this->createDraftContent(['slug' => 'bozza-segreta']);

        $this->getJson(self::LIST_ENDPOINT.'/bozza-segreta')
            ->assertNotFound();

        $this->assertSame('draft', $draft->status->value);
    }

    public function test_card_dto_shape_matches_expected_keys(): void
    {
        $media = EditorialMedia::factory()->create();
        $content = $this->createPublishedContent([
            'title' => 'Card shape test',
            'excerpt' => 'Descrizione card',
            'read_minutes' => 6,
            'featured' => true,
            'content_type' => EditorialContentType::Article,
        ], $media);

        $response = $this->getJson(self::LIST_ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'contents' => [[
                        'id',
                        'type',
                        'title',
                        'description',
                        'category',
                        'readMinutes',
                        'url',
                        'image',
                        'featured',
                    ]],
                ],
                'meta' => ['page', 'limit', 'total', 'last_page'],
            ]);

        $card = $response->json('data.contents.0');

        $this->assertSame($content->uuid, $card['id']);
        $this->assertSame('article', $card['type']);
        $this->assertSame('Card shape test', $card['title']);
        $this->assertSame('Descrizione card', $card['description']);
        $this->assertSame('Guide', $card['category']);
        $this->assertSame(6, $card['readMinutes']);
        $this->assertSame('/magazine/guide/'.$content->slug, $card['url']);
        $this->assertTrue($card['featured']);
        $this->assertNotEmpty($card['image']);
    }

    public function test_rubrics_endpoint_returns_active_rubrics_with_published_count(): void
    {
        $this->createPublishedContent(['rubric_id' => $this->guideRubric->id, 'rubric_slug' => 'guide']);
        $this->createPublishedContent(['rubric_id' => $this->guideRubric->id, 'rubric_slug' => 'guide']);
        $this->createPublishedContent([
            'content_type' => EditorialContentType::Story,
            'rubric_id' => $this->storieRubric->id,
            'rubric_slug' => 'storie',
        ]);
        $this->createDraftContent(['rubric_id' => $this->guideRubric->id, 'rubric_slug' => 'guide']);

        $response = $this->getJson(self::RUBRICS_ENDPOINT);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'rubrics' => [[
                        'id',
                        'slug',
                        'name',
                        'description',
                        'published_count',
                    ]],
                ],
            ]);

        $guide = collect($response->json('data.rubrics'))->firstWhere('slug', 'guide');
        $storie = collect($response->json('data.rubrics'))->firstWhere('slug', 'storie');

        $this->assertSame(2, $guide['published_count']);
        $this->assertSame(1, $storie['published_count']);
    }

    public function test_list_filters_by_type(): void
    {
        $this->createPublishedContent([
            'content_type' => EditorialContentType::Article,
            'title' => 'Articolo filtro',
        ]);
        $this->createPublishedContent([
            'content_type' => EditorialContentType::Story,
            'title' => 'Storia filtro',
            'rubric_id' => $this->storieRubric->id,
            'rubric_slug' => 'storie',
        ]);
        $this->createPublishedContent([
            'content_type' => EditorialContentType::Interview,
            'title' => 'Intervista filtro',
            'rubric_id' => EditorialRubric::query()->where('slug', 'interviste')->firstOrFail()->id,
            'rubric_slug' => 'interviste',
        ]);

        $response = $this->getJson(self::LIST_ENDPOINT.'?type=story');

        $response->assertOk()
            ->assertJsonCount(1, 'data.contents')
            ->assertJsonPath('data.contents.0.type', 'story')
            ->assertJsonPath('data.contents.0.title', 'Storia filtro');
    }

    public function test_published_detail_returns_public_resource(): void
    {
        $content = $this->createPublishedContent([
            'slug' => 'dettaglio-pubblico',
            'subtitle' => 'Sottotitolo',
            'read_minutes' => 8,
        ]);

        $this->getJson(self::LIST_ENDPOINT.'/dettaglio-pubblico')
            ->assertOk()
            ->assertJsonPath('data.content.uuid', $content->uuid)
            ->assertJsonPath('data.content.title', $content->title)
            ->assertJsonPath('data.content.subtitle', 'Sottotitolo')
            ->assertJsonPath('data.content.read_minutes', 8)
            ->assertJsonStructure([
                'data' => [
                    'content' => [
                        'body_blocks',
                        'authors',
                        'seo',
                        'published_at',
                    ],
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPublishedContent(array $overrides = [], ?EditorialMedia $media = null): EditorialContent
    {
        $media ??= EditorialMedia::factory()->create();

        return EditorialContent::factory()
            ->published()
            ->withRubric($this->guideRubric)
            ->withHeroMedia($media)
            ->create(array_merge([
                'sector_id' => $this->sector->id,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
            ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftContent(array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()
            ->withRubric($this->guideRubric)
            ->create(array_merge([
                'sector_id' => $this->sector->id,
                'status' => EditorialContentStatus::Draft,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
            ], $overrides));
    }
}
