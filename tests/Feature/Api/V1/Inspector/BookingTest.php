<?php

namespace Tests\Feature\Api\V1\Inspector;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Inspector side: seeing open bookings, accepting, starting, completing.
 */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        // 06:00 UTC = 11:30 in Colombo on 10 Nov.
        $this->travelTo('2026-11-10 06:00:00');

        $this->inspector = User::factory()->inspector()->create();
        Sanctum::actingAs($this->inspector);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/api/v1/inspector/bookings'],
            'view' => ['get', '/api/v1/inspector/bookings/{id}'],
            'accept' => ['post', '/api/v1/inspector/bookings/{id}/accept'],
            'start' => ['post', '/api/v1/inspector/bookings/{id}/start'],
            'complete' => ['post', '/api/v1/inspector/bookings/{id}/complete'],
        ];
    }

    #[DataProvider('routes')]
    public function test_normal_users_are_forbidden(string $method, string $uri): void
    {
        $uri = str_replace('{id}', (string) Booking::factory()->create()->id, $uri);

        Sanctum::actingAs(User::factory()->create());
        $this->json($method, $uri)->assertForbidden();
    }

    public function test_admin_can_list_and_view_any_booking(): void
    {
        $other = User::factory()->inspector()->create();
        $pending = Booking::factory()->create(['scheduled_date' => '2026-11-11']);
        $theirs = Booking::factory()->accepted($other)->create(['scheduled_date' => '2026-11-11']);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/inspector/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->getJson("/api/v1/inspector/bookings/{$theirs->id}")
            ->assertOk()
            ->assertJsonPath('data.inspector.id', $other->id)
            ->assertJsonPath('data.customer.id', $theirs->user_id);
    }

    public function test_admin_can_accept_start_and_complete(): void
    {
        $admin = Sanctum::actingAs(User::factory()->admin()->create());
        $booking = Booking::factory()->create(['scheduled_date' => '2026-11-10']);

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.inspector.id', $admin->id);

        $this->getJson('/api/v1/inspector/bookings?scope=mine')
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->getJson('/api/v1/inspector/bookings?scope=history')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $booking->id);
    }

    public function test_admin_cannot_start_another_inspectors_booking(): void
    {
        $other = User::factory()->inspector()->create();
        $theirs = Booking::factory()->accepted($other)->create(['scheduled_date' => '2026-11-10']);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/inspector/bookings/{$theirs->id}/start")->assertNotFound();
        $this->postJson("/api/v1/inspector/bookings/{$theirs->id}/complete")->assertNotFound();
    }

    public function test_available_shows_open_bookings_from_today_soonest_first(): void
    {
        $other = User::factory()->inspector()->create();
        $tomorrowEvening = Booking::factory()->create(['scheduled_date' => '2026-11-11', 'time_slot' => 'evening']);
        $tomorrowMorning = Booking::factory()->create(['scheduled_date' => '2026-11-11', 'time_slot' => 'morning']);
        $today = Booking::factory()->create(['scheduled_date' => '2026-11-10']);
        Booking::factory()->create(['scheduled_date' => '2026-11-09']); // past
        Booking::factory()->accepted($other)->create(['scheduled_date' => '2026-11-12']);
        Booking::factory()->cancelled()->create(['scheduled_date' => '2026-11-12']);

        $this->getJson('/api/v1/inspector/bookings?scope=available')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $today->id)
            ->assertJsonPath('data.1.id', $tomorrowMorning->id)
            ->assertJsonPath('data.2.id', $tomorrowEvening->id);

        // "available" is the default.
        $this->getJson('/api/v1/inspector/bookings')->assertJsonCount(3, 'data');
    }

    public function test_inspector_sees_customer_details(): void
    {
        $customer = User::factory()->create(['first_name' => 'Nimal', 'phone' => '+94771234567']);
        $booking = Booking::factory()->for($customer)->create(['notes' => 'Call when near']);

        $this->getJson("/api/v1/inspector/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.first_name', 'Nimal')
            ->assertJsonPath('data.customer.phone', '+94771234567')
            ->assertJsonPath('data.notes', 'Call when near')
            ->assertJsonPath('data.location.latitude', 6.9271);
    }

    public function test_accept_claims_the_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.inspector.id', $this->inspector->id);

        $booking->refresh();
        $this->assertSame(BookingStatus::Accepted, $booking->status);
        $this->assertSame($this->inspector->id, $booking->inspector_id);
        $this->assertNotNull($booking->accepted_at);

        // Gone from available, now in mine.
        $this->getJson('/api/v1/inspector/bookings?scope=available')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/inspector/bookings?scope=mine')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $booking->id);
    }

    public function test_second_inspector_gets_409(): void
    {
        $booking = Booking::factory()->create();
        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")->assertOk();

        Sanctum::actingAs(User::factory()->inspector()->create());

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_ALREADY_TAKEN');

        // And they can't see it any more.
        $this->getJson("/api/v1/inspector/bookings/{$booking->id}")->assertNotFound();

        $this->assertSame($this->inspector->id, $booking->fresh()->inspector_id);
    }

    public function test_accepting_twice_is_harmless(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")->assertOk();
        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.inspector.id', $this->inspector->id);
    }

    public function test_cannot_accept_cancelled_or_past_bookings(): void
    {
        $cancelled = Booking::factory()->cancelled()->create();
        $past = Booking::factory()->create(['scheduled_date' => '2026-11-09']);

        $this->postJson("/api/v1/inspector/bookings/{$cancelled->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_BOOKING_STATUS');

        $this->postJson("/api/v1/inspector/bookings/{$past->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_BOOKING_STATUS');
    }

    public function test_start_and_complete_on_the_scheduled_day(): void
    {
        $booking = Booking::factory()->accepted($this->inspector)->create(['scheduled_date' => '2026-11-10']);

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $booking->refresh();
        $this->assertNotNull($booking->started_at);
        $this->assertNotNull($booking->completed_at);

        $this->getJson('/api/v1/inspector/bookings?scope=mine')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/inspector/bookings?scope=history')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $booking->id);
    }

    public function test_cannot_start_before_the_scheduled_day(): void
    {
        $booking = Booking::factory()->accepted($this->inspector)->create(['scheduled_date' => '2026-11-12']);

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/start")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You can start this inspection on its scheduled date.');
    }

    public function test_steps_must_happen_in_order(): void
    {
        $accepted = Booking::factory()->accepted($this->inspector)->create(['scheduled_date' => '2026-11-10']);
        $cancelled = Booking::factory()->accepted($this->inspector)->create(['scheduled_date' => '2026-11-10']);
        $cancelled->forceFill(['status' => BookingStatus::Cancelled])->save();

        // Complete before start.
        $this->postJson("/api/v1/inspector/bookings/{$accepted->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_BOOKING_STATUS');

        // The customer cancelled.
        $this->postJson("/api/v1/inspector/bookings/{$cancelled->id}/start")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This booking was cancelled by the customer.');
    }

    public function test_cannot_work_on_another_inspectors_booking(): void
    {
        $other = User::factory()->inspector()->create();
        $theirs = Booking::factory()->accepted($other)->create(['scheduled_date' => '2026-11-10']);
        $pending = Booking::factory()->create(['scheduled_date' => '2026-11-10']);

        $this->getJson("/api/v1/inspector/bookings/{$theirs->id}")->assertNotFound();
        $this->postJson("/api/v1/inspector/bookings/{$theirs->id}/start")->assertNotFound();
        $this->postJson("/api/v1/inspector/bookings/{$theirs->id}/complete")->assertNotFound();

        // A pending booking must be accepted before it can be started.
        $this->postJson("/api/v1/inspector/bookings/{$pending->id}/start")->assertNotFound();
    }

    public function test_customer_sees_the_inspector_after_acceptance(): void
    {
        $customer = User::factory()->create();
        $this->inspector->forceFill(['phone' => '+94711234567'])->save();
        $booking = Booking::factory()->for($customer)->create();

        $this->postJson("/api/v1/inspector/bookings/{$booking->id}/accept")->assertOk();

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.inspector.phone', '+94711234567')
            ->assertJsonMissingPath('data.customer');
    }
}
