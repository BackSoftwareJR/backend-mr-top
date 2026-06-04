<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_read_profile(): void
    {
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'name' => 'Mario Rossi',
            'phone' => '+39 333 111 2222',
        ]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/user/profile')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Mario Rossi')
            ->assertJsonPath('data.user.phone', '+39 333 111 2222')
            ->assertJsonPath('data.user.email', $consumer->email);
    }

    public function test_consumer_can_update_name_and_phone(): void
    {
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'name' => 'Mario Rossi',
            'phone' => '+39 333 111 2222',
        ]);
        Sanctum::actingAs($consumer);

        $response = $this->patchJson('/api/v1/user/profile', [
            'name' => 'Giulia Bianchi',
            'phone' => '+39 340 999 8888',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Giulia Bianchi')
            ->assertJsonPath('data.user.phone', '+39 340 999 8888');

        $this->assertDatabaseHas('users', [
            'id' => $consumer->id,
            'name' => 'Giulia Bianchi',
            'phone' => '+39 340 999 8888',
        ]);
    }

    public function test_consumer_can_clear_phone(): void
    {
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'phone' => '+39 333 111 2222',
        ]);
        Sanctum::actingAs($consumer);

        $this->patchJson('/api/v1/user/profile', [
            'phone' => null,
        ])->assertOk()
            ->assertJsonPath('data.user.phone', null);

        $this->assertDatabaseHas('users', [
            'id' => $consumer->id,
            'phone' => null,
        ]);
    }

    public function test_profile_update_validates_name_length(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->patchJson('/api/v1/user/profile', [
            'name' => str_repeat('a', 256),
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->patchJson('/api/v1/user/profile', [
            'name' => 'Test',
        ])->assertUnauthorized();
    }
}
