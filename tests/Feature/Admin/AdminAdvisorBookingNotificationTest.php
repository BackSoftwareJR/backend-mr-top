<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppointmentType;
use App\Enums\UserType;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAdvisorBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_booking_surfaces_in_admin_notifications(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);

        Appointment::query()->create([
            'user_id' => $consumer->id,
            'client_name' => 'Giulia Bianchi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '09:00:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Nuova consulenza advisor',
            ])
            ->assertJsonFragment([
                'href' => '/admin/advisor-bookings',
            ]);

        $notifications = $this->getJson('/api/v1/admin/notifications')->json('data.notifications');
        $advisorNotification = collect($notifications)->first(
            fn (array $notification): bool => $notification['title'] === 'Nuova consulenza advisor',
        );

        $this->assertNotNull($advisorNotification);
        $this->assertStringContainsString('Giulia Bianchi', $advisorNotification['message']);
    }
}
