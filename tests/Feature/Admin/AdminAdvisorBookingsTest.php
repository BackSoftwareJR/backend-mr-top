<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppointmentType;
use App\Enums\UserType;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAdvisorBookingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_superadmin_lists_advisor_bookings_split_upcoming_and_past(): void
    {
        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'consumer@example.com',
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'user_id' => $consumer->id,
            'status' => 'processing',
            'title' => 'Ricerca per la Mamma',
            'payload' => [],
            'contact_name' => 'Mario Rossi',
        ]);

        Appointment::query()->create([
            'user_id' => $consumer->id,
            'lead_id' => $lead->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        Appointment::query()->create([
            'user_id' => $consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->subDays(2)->toDateString(),
            'scheduled_time' => '14:00:00',
            'type' => AppointmentType::Advisor,
        ]);

        $response = $this->getJson('/api/v1/admin/advisor-bookings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'upcoming' => [
                        '*' => [
                            'id',
                            'consumer_name',
                            'consumer_email',
                            'lead_title',
                            'scheduled_at',
                            'scheduled_date',
                            'scheduled_time',
                        ],
                    ],
                    'past' => [
                        '*' => [
                            'id',
                            'consumer_name',
                            'consumer_email',
                            'lead_title',
                            'scheduled_at',
                            'scheduled_date',
                            'scheduled_time',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.upcoming'));
        $this->assertCount(1, $response->json('data.past'));
        $this->assertSame('Mario Rossi', $response->json('data.upcoming.0.consumer_name'));
        $this->assertSame('consumer@example.com', $response->json('data.upcoming.0.consumer_email'));
        $this->assertSame('Ricerca per la Mamma', $response->json('data.upcoming.0.lead_title'));
        $this->assertNotEmpty($response->json('data.upcoming.0.scheduled_at'));
    }

    public function test_non_superadmin_cannot_list_advisor_bookings(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/admin/advisor-bookings')
            ->assertForbidden();
    }
}
