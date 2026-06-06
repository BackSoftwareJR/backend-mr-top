<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use App\Models\EditorialIndexRule;
use App\Models\EditorialMedia;
use App\Models\EditorialRubric;
use App\Models\Sector;
use Database\Seeders\EditorialPermissionSeeder;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorialFeedsTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $guideRubric;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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
        $this->seed(EditorialPermissionSeeder::class);
        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_sitemap_command_generates_valid_xml_with_published_url(): void
    {
        $content = $this->createPublishedContent([
            'slug' => 'guida-sitemap',
            'title' => 'Guida sitemap test',
        ]);

        Artisan::call('editorial:generate-sitemaps');

        $xml = Storage::disk('public')->get('editorial/sitemap.xml');

        $this->assertNotNull($xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('https://wenando.com/magazine/guide/guida-sitemap', $xml);
        $this->assertStringContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('<priority>0.8</priority>', $xml);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://wenando.com/magazine/guide/guida-sitemap', false);
    }

    public function test_draft_content_is_excluded_from_sitemap(): void
    {
        $this->createPublishedContent([
            'slug' => 'pubblicato-sitemap',
        ]);

        EditorialContent::factory()
            ->withRubric($this->guideRubric)
            ->create([
                'sector_id' => $this->sector->id,
                'slug' => 'bozza-sitemap',
                'status' => EditorialContentStatus::Draft,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
            ]);

        Artisan::call('editorial:generate-sitemaps');

        $xml = Storage::disk('public')->get('editorial/sitemap.xml');

        $this->assertNotNull($xml);
        $this->assertStringContainsString('pubblicato-sitemap', $xml);
        $this->assertStringNotContainsString('bozza-sitemap', $xml);
    }

    public function test_noindex_content_is_excluded_from_sitemap(): void
    {
        $this->createPublishedContent([
            'slug' => 'indicizzato',
        ]);

        $this->createPublishedContent([
            'slug' => 'noindex-escluso',
            'noindex' => true,
        ]);

        Artisan::call('editorial:generate-sitemaps');

        $xml = Storage::disk('public')->get('editorial/sitemap.xml');

        $this->assertNotNull($xml);
        $this->assertStringContainsString('indicizzato', $xml);
        $this->assertStringNotContainsString('noindex-escluso', $xml);
    }

    public function test_rubric_index_rule_can_exclude_content_from_sitemap(): void
    {
        EditorialIndexRule::factory()->create([
            'rubric_slug' => 'guide',
            'include_in_sitemap' => false,
            'is_active' => true,
        ]);

        $this->createPublishedContent([
            'slug' => 'guida-esclusa',
        ]);

        Artisan::call('editorial:generate-sitemaps');

        $xml = Storage::disk('public')->get('editorial/sitemap.xml');

        $this->assertNotNull($xml);
        $this->assertStringNotContainsString('guida-esclusa', $xml);
    }

    public function test_feed_xml_returns_200_with_published_article_item(): void
    {
        $media = EditorialMedia::factory()->create();

        $this->createPublishedContent([
            'slug' => 'articolo-feed',
            'title' => 'Articolo RSS di test',
            'content_type' => EditorialContentType::Article,
            'excerpt' => 'Descrizione breve per il feed RSS.',
            'hero_media_id' => $media->id,
        ]);

        $response = $this->get('/feed.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->assertSee('<rss version="2.0"', false);
        $response->assertSee('<item>', false);
        $response->assertSee('Articolo RSS di test', false);
        $response->assertSee('https://wenando.com/magazine/guide/articolo-feed', false);
        $response->assertSee('<enclosure', false);
    }

    public function test_robots_txt_references_sitemap_and_allows_magazine(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Allow: /magazine/', false);
        $response->assertSee('Disallow: /api/', false);
        $response->assertSee('Sitemap: https://wenando.com/sitemap.xml', false);
    }

    public function test_llms_txt_returns_200_with_site_description_and_sitemap_link(): void
    {
        $this->createPublishedContent([
            'slug' => 'llms-priority',
            'title' => 'Contenuto prioritario llms',
        ]);

        $response = $this->get('/llms.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('# Wenando — Assistenza anziani in Italia', false);
        $response->assertSee('## Policy di citazione', false);
        $response->assertSee('https://wenando.com/sitemap.xml', false);
        $response->assertSee('https://wenando.com/magazine/guide/llms-priority', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPublishedContent(array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()
            ->published()
            ->withRubric($this->guideRubric)
            ->create(array_merge([
                'sector_id' => $this->sector->id,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
            ], $overrides));
    }
}
