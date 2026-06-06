<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EditorialContent;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialContentMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_migrations_run_and_factory_persists_body_blocks_json(): void
    {
        $content = EditorialContent::factory()->create();

        $this->assertDatabaseHas('editorial_contents', [
            'id' => $content->id,
            'slug' => $content->slug,
            'content_type' => 'article',
            'status' => 'draft',
        ]);

        $content->refresh();

        $this->assertIsArray($content->body_blocks);
        $this->assertCount(2, $content->body_blocks);
        $this->assertSame('heading', $content->body_blocks[0]['type']);
        $this->assertSame('paragraph', $content->body_blocks[1]['type']);
        $this->assertArrayHasKey('data', $content->body_blocks[0]);
        $this->assertSame('Introduzione', $content->body_blocks[0]['data']['text']);
    }
}
