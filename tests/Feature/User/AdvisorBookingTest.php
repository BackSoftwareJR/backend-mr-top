<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\AppointmentType;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Mail\AdvisorBookingCancellationMail;
use App\Mail\AdvisorBookingConfirmationMail;
use App\Mail\AdvisorBookingRescheduleMail;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvisorBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
        ]);
    }

    public function test_advisor_booking_queues_confirmation_email(): void
    {
        Mail::fake();

        config(['wenando.privacy_contact_email' => 'ops@wenando.com']);

        Sanctum::actingAs($this->consumer);

        $this->postJson('/api/v1/user/advisor-bookings', [
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:30',
        ])->assertOk();

        Mail::assertQueued(AdvisorBookingConfirmationMail::class, function (AdvisorBookingConfirmationMail $mail): bool {
            return $mail->hasTo($this->consumer->email)
                && $mail->hasBcc('ops@wenando.com')
                && $mail->appointment->client_name === 'Mario Rossi'
                && str_contains($mail->render(), '/admin/advisor-bookings');
        });
    }

    public function test_consumer_can_create_advisor_booking(): void
    {
        Mail::fake();

        Sanctum::actingAs($this->consumer);

        $response = $this->postJson('/api/v1/user/advisor-bookings', [
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:30',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['booking_id']]);

        $bookingId = $response->json('data.booking_id');

        $this->assertDatabaseHas('appointments', [
            'id' => $bookingId,
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'type' => AppointmentType::Advisor->value,
        ]);
    }

    public function test_consumer_can_list_advisor_bookings_split_upcoming_and_past(): void
    {
        Sanctum::actingAs($this->consumer);

        $lead = $this->createLead([
            'title' => 'Ricerca per la Mamma',
            'public_ref' => 'LD-2001',
        ]);

        Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'lead_id' => $lead->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->subDays(2)->toDateString(),
            'scheduled_time' => '14:00:00',
            'type' => AppointmentType::Advisor,
        ]);

        $response = $this->getJson('/api/v1/user/advisor-bookings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'upcoming' => [
                        '*' => ['id', 'scheduled_date', 'scheduled_time', 'name', 'lead_title'],
                    ],
                    'past' => [
                        '*' => ['id', 'scheduled_date', 'scheduled_time', 'name', 'lead_title'],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.upcoming'));
        $this->assertCount(1, $response->json('data.past'));
        $this->assertSame('Ricerca per la Mamma', $response->json('data.upcoming.0.lead_title'));
        $this->assertNull($response->json('data.past.0.lead_title'));
    }

    public function test_list_advisor_bookings_requires_consumer_auth(): void
    {
        $this->getJson('/api/v1/user/advisor-bookings')->assertUnauthorized();
    }

    public function test_advisor_booking_stores_lead_id_when_linked(): void
    {
        Sanctum::actingAs($this->consumer);

        $lead = $this->createLead([
            'public_ref' => 'LD-1001',
            'contact_name' => 'Mario Rossi',
        ]);

        $response = $this->postJson('/api/v1/user/advisor-bookings', [
            'lead_uuid' => $lead->uuid,
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '14:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'user_id' => $this->consumer->id,
        ]);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $this->consumer->id,
            'lead_id' => $lead->id,
            'type' => AppointmentType::Advisor->value,
        ]);

        $this->assertSame(1, Appointment::query()->where('user_id', $this->consumer->id)->count());
    }

    public function test_advisor_booking_rejects_lead_owned_by_another_user(): void
    {
        Sanctum::actingAs($this->consumer);

        $otherUser = User::factory()->create(['user_type' => UserType::Consumer]);
        $lead = $this->createLead([
            'user_id' => $otherUser->id,
            'public_ref' => 'LD-1002',
            'contact_name' => 'Altri',
            'location_label' => 'Roma (RM)',
        ]);

        $this->postJson('/api/v1/user/advisor-bookings', [
            'lead_uuid' => $lead->uuid,
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '09:00',
        ])->assertForbidden();
    }

    public function test_advisor_booking_requires_consumer_auth(): void
    {
        $this->postJson('/api/v1/user/advisor-bookings', [
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:30',
        ])->assertUnauthorized();
    }

    public function test_advisor_booking_rejects_partner_user(): void
    {
        $partner = User::factory()->create(['user_type' => UserType::B2b]);
        Sanctum::actingAs($partner);

        $this->postJson('/api/v1/user/advisor-bookings', [
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:30',
        ])->assertForbidden();
    }

    public function test_advisor_booking_validates_required_fields(): void
    {
        Sanctum::actingAs($this->consumer);

        $this->postJson('/api/v1/user/advisor-bookings', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['name', 'scheduled_date', 'scheduled_time'],
                ],
            ]);
    }

    public function test_advisor_booking_rejects_past_date(): void
    {
        Sanctum::actingAs($this->consumer);

        $this->postJson('/api/v1/user/advisor-bookings', [
            'name' => 'Mario Rossi',
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_time' => '10:30',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['scheduled_date']]]);
    }

    public function test_advisor_booking_cancel_queues_cancellation_email(): void
    {
        Mail::fake();

        config(['wenando.privacy_contact_email' => 'ops@wenando.com']);

        Sanctum::actingAs($this->consumer);

        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->deleteJson("/api/v1/user/advisor-bookings/{$booking->id}")->assertOk();

        Mail::assertQueued(AdvisorBookingCancellationMail::class, function (AdvisorBookingCancellationMail $mail): bool {
            return $mail->hasTo($this->consumer->email)
                && $mail->hasBcc('ops@wenando.com')
                && $mail->appointment->client_name === 'Mario Rossi'
                && str_contains($mail->render(), '/admin/advisor-bookings');
        });
    }

    public function test_advisor_booking_reschedule_queues_reschedule_email(): void
    {
        Mail::fake();

        config(['wenando.privacy_contact_email' => 'ops@wenando.com']);

        Sanctum::actingAs($this->consumer);

        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $newDate = now()->addDays(5)->toDateString();

        $this->patchJson("/api/v1/user/advisor-bookings/{$booking->id}", [
            'scheduled_date' => $newDate,
            'scheduled_time' => '14:00',
        ])->assertOk();

        Mail::assertQueued(AdvisorBookingRescheduleMail::class, function (AdvisorBookingRescheduleMail $mail) use ($newDate): bool {
            return $mail->hasTo($this->consumer->email)
                && $mail->hasBcc('ops@wenando.com')
                && $mail->appointment->client_name === 'Mario Rossi'
                && $mail->appointment->scheduled_date?->toDateString() === $newDate
                && substr((string) $mail->appointment->scheduled_time, 0, 5) === '14:00'
                && str_contains($mail->render(), '/admin/advisor-bookings');
        });
    }

    public function test_consumer_can_reschedule_advisor_booking(): void
    {
        Sanctum::actingAs($this->consumer);

        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $newDate = now()->addDays(5)->toDateString();

        $this->patchJson("/api/v1/user/advisor-bookings/{$booking->id}", [
            'scheduled_date' => $newDate,
            'scheduled_time' => '14:00',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking.scheduled_date', $newDate)
            ->assertJsonPath('data.booking.scheduled_time', '14:00');

        $booking->refresh();
        $this->assertSame($newDate, $booking->scheduled_date->toDateString());
        $this->assertSame('14:00', substr((string) $booking->scheduled_time, 0, 5));
    }

    public function test_reschedule_advisor_booking_requires_consumer_auth(): void
    {
        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->patchJson("/api/v1/user/advisor-bookings/{$booking->id}", [
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '14:00',
        ])->assertUnauthorized();
    }

    public function test_reschedule_advisor_booking_rejects_other_users_booking(): void
    {
        Sanctum::actingAs($this->consumer);

        $otherUser = User::factory()->create(['user_type' => UserType::Consumer]);
        $booking = Appointment::query()->create([
            'user_id' => $otherUser->id,
            'client_name' => 'Altri',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->patchJson("/api/v1/user/advisor-bookings/{$booking->id}", [
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '14:00',
        ])->assertForbidden();
    }

    public function test_reschedule_advisor_booking_rejects_past_date(): void
    {
        Sanctum::actingAs($this->consumer);

        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->patchJson("/api/v1/user/advisor-bookings/{$booking->id}", [
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_time' => '14:00',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['scheduled_date']]]);
    }

    public function test_consumer_can_cancel_advisor_booking(): void
    {
        Sanctum::actingAs($this->consumer);

        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->deleteJson("/api/v1/user/advisor-bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cancelled', true);

        $this->assertSoftDeleted('appointments', ['id' => $booking->id]);
    }

    public function test_cancel_advisor_booking_requires_consumer_auth(): void
    {
        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->deleteJson("/api/v1/user/advisor-bookings/{$booking->id}")
            ->assertUnauthorized();
    }

    public function test_cancel_advisor_booking_rejects_other_users_booking(): void
    {
        Sanctum::actingAs($this->consumer);

        $otherUser = User::factory()->create(['user_type' => UserType::Consumer]);
        $booking = Appointment::query()->create([
            'user_id' => $otherUser->id,
            'client_name' => 'Altri',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->deleteJson("/api/v1/user/advisor-bookings/{$booking->id}")
            ->assertForbidden();
    }

    public function test_cancelled_advisor_booking_is_excluded_from_list(): void
    {
        Sanctum::actingAs($this->consumer);

        $booking = Appointment::query()->create([
            'user_id' => $this->consumer->id,
            'client_name' => 'Mario Rossi',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:30:00',
            'type' => AppointmentType::Advisor,
        ]);

        $this->deleteJson("/api/v1/user/advisor-bookings/{$booking->id}")->assertOk();

        $response = $this->getJson('/api/v1/user/advisor-bookings')->assertOk();
        $this->assertCount(0, $response->json('data.upcoming'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLead(array $overrides = []): Lead
    {
        $sector = Sector::query()->firstOrFail();

        return Lead::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'status' => LeadStatus::Routed,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                'budget' => ['min' => 1500, 'max' => 2500],
                'contact' => ['nome' => 'Mario', 'telefono' => '+39 333 123 4567'],
            ],
            'contact_name' => 'Mario Rossi',
            'contact_phone' => '+39 333 123 4567',
            'location_label' => 'Milano (MI)',
            'budget_min' => 1500,
            'budget_max' => 2500,
        ], $overrides));
    }
}
