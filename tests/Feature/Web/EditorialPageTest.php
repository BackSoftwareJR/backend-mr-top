<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\EditorialContentStatus;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\EditorialMedia;
use App\Models\EditorialRubric;
use App\Models\Sector;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialPageTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $guideRubric;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'editorial.site_url' => 'https://wenando.com',
            'app.url' => 'https://wenando.com',
        ]);

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_published_content_returns_200_with_title_in_html(): void
    {
        $content = $this->createPublishedContent([
            'slug' => 'guida-badante',
            'title' => 'Guida completa badante convivente',
            'seo_pack' => [
                'seo_title' => 'Guida badante convivente 2026',
                'meta_description' => 'Tutto sui costi e i contratti della badante convivente.',
            ],
        ]);

        $response = $this->get('/magazine/guide/guida-badante');

        $response->assertOk();
        $response->assertSee('<title>Guida badante convivente 2026 | Wenando</title>', false);
        $response->assertSee('<h1 class="editorial-title"', false);
        $response->assertSee('Guida completa badante convivente', false);
        $response->assertSee('Introduzione', false);
    }

    public function test_draft_content_returns_404(): void
    {
        EditorialContent::factory()
            ->withRubric($this->guideRubric)
            ->create([
                'sector_id' => $this->sector->id,
                'slug' => 'bozza-nascosta',
                'status' => EditorialContentStatus::Draft,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
            ]);

        $this->get('/magazine/guide/bozza-nascosta')->assertNotFound();
    }

    public function test_noindex_published_content_returns_404(): void
    {
        $this->createPublishedContent([
            'slug' => 'contenuto-noindex',
            'noindex' => true,
        ]);

        $this->get('/magazine/guide/contenuto-noindex')->assertNotFound();
    }

    public function test_meta_description_is_present(): void
    {
        $this->createPublishedContent([
            'slug' => 'meta-test',
            'seo_pack' => [
                'meta_description' => 'Descrizione SEO per crawler e social preview.',
            ],
        ]);

        $response = $this->get('/magazine/guide/meta-test');

        $response->assertOk();
        $response->assertSee(
            '<meta name="description" content="Descrizione SEO per crawler e social preview.">',
            false,
        );
        $response->assertSee(
            '<meta property="og:description" content="Descrizione SEO per crawler e social preview.">',
            false,
        );
    }

    public function test_json_ld_script_is_present(): void
    {
        $this->createPublishedContent([
            'slug' => 'jsonld-test',
            'title' => 'Articolo JSON-LD',
            'body_blocks' => [
                [
                    'id' => 'faq-1',
                    'type' => 'faq',
                    'data' => [
                        'items' => [
                            [
                                'question' => 'Quanto costa una badante?',
                                'answer' => 'In media tra 1.200€ e 1.800€ al mese.',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get('/magazine/guide/jsonld-test');

        $response->assertOk();
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@graph"', false);
        $response->assertSee('"@type":"Article"', false);
        $response->assertSee('"@type":"FAQPage"', false);
        $response->assertSee('Quanto costa una badante?', false);
    }

    public function test_structure_disclaimer_when_company_content(): void
    {
        $company = Company::factory()->create([
            'sector_id' => $this->sector->id,
        ]);

        $this->createPublishedContent([
            'slug' => 'contenuto-struttura',
            'company_id' => $company->id,
        ]);

        $response = $this->get('/magazine/guide/contenuto-struttura');

        $response->assertOk();
        $response->assertSee('editorial-disclaimer', false);
        $response->assertSee('Contenuto redatto dalla struttura', false);
    }

    public function test_magazine_hub_lists_published_contents(): void
    {
        $this->createPublishedContent([
            'title' => 'Primo articolo hub',
            'slug' => 'primo-hub',
        ]);
        $this->createPublishedContent([
            'title' => 'Secondo articolo hub',
            'slug' => 'secondo-hub',
        ]);

        $response = $this->get('/magazine');

        $response->assertOk();
        $response->assertSee('Magazine Wenando', false);
        $response->assertSee('Primo articolo hub', false);
        $response->assertSee('Secondo articolo hub', false);
        $response->assertSee('/magazine/guide/primo-hub', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPublishedContent(array $overrides = []): EditorialContent
    {
        $media = EditorialMedia::factory()->create();

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
}
