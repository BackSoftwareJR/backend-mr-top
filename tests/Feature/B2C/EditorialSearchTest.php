<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EditorialSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/b2c/search/editorial';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('search-editorial');
    }

    public function test_editorial_returns_items_envelope(): void
    {
        $response = $this->getJson(self::ENDPOINT.'?q=badante&limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['id', 'title', 'summary', 'url', 'relevanceReason', 'sector'],
                    ],
                ],
            ]);

        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertLessThanOrEqual(5, count($items));
        $this->assertStringContainsString('badante', mb_strtolower($items[0]['title'].$items[0]['summary']));
    }

    public function test_editorial_filters_by_query_keywords(): void
    {
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

    public function test_editorial_respects_sector_and_limit(): void
    {
        $response = $this->getJson(self::ENDPOINT.'?sector=elder_care&limit=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data.items');

        foreach ($response->json('data.items') as $item) {
            $this->assertSame('elder_care', $item['sector']);
            $this->assertNotSame('#', $item['url']);
        }
    }

    public function test_editorial_returns_empty_items_for_unknown_sector(): void
    {
        $response = $this->getJson(self::ENDPOINT.'?sector=pet_care');

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
}
