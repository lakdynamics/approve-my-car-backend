<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Auth\SignsInUsers;
use Tests\TestCase;

/**
 * How bookings react to changes elsewhere: deleting a vehicle or an
 * account, and demoting an inspector.
 */
class BookingSideEffectsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_vehicle_with_an_active_booking_can_be_deleted(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $vehicle = Vehicle::factory()->for($user)->create(['registration_number' => 'CAB 9999']);
        $booking = Booking::factory()->for($user)->for($vehicle)->create();

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertNoContent();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicle.registration_number', 'CAB 9999');
    }

    public function test_deleted_vehicle_still_shows_on_old_bookings(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $vehicle = Vehicle::factory()->for($user)->create(['registration_number' => 'CAB 1234']);
        $booking = Booking::factory()->for($user)->for($vehicle)->cancelled()->create();
        $vehicle->delete();

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicle.registration_number', 'CAB 1234');
    }

    public function test_deleting_an_account_cancels_bookings_not_yet_started(): void
    {
        $user = $this->createUser();
        $inspector = User::factory()->inspector()->create();
        $pending = Booking::factory()->for($user)->create();
        $accepted = Booking::factory()->for($user)->accepted($inspector)->create();
        $started = Booking::factory()->for($user)->inProgress($inspector)->create();

        $tokens = $this->signIn($user);
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/v1/me', ['password' => self::PASSWORD], ['Authorization' => 'Bearer '.$tokens['access_token']])
            ->assertNoContent();

        $this->assertSame(BookingStatus::Cancelled, $pending->fresh()->status);
        $this->assertSame('Account deleted', $pending->fresh()->cancellation_reason);
        $this->assertSame(BookingStatus::Cancelled, $accepted->fresh()->status);
        $this->assertSame(BookingStatus::InProgress, $started->fresh()->status);
    }

    public function test_demoting_an_inspector_releases_their_bookings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $inspector = User::factory()->inspector()->create();
        $accepted = Booking::factory()->accepted($inspector)->create();
        $started = Booking::factory()->inProgress($inspector)->create();
        $done = Booking::factory()->completed($inspector)->create();

        $this->deleteJson("/api/v1/admin/inspectors/{$inspector->id}")->assertOk();

        foreach ([$accepted, $started] as $booking) {
            $booking->refresh();
            $this->assertSame(BookingStatus::Pending, $booking->status);
            $this->assertNull($booking->inspector_id);
            $this->assertNull($booking->accepted_at);
            $this->assertNull($booking->started_at);
        }

        // Finished work keeps its record.
        $this->assertSame($inspector->id, $done->fresh()->inspector_id);
        $this->assertSame(BookingStatus::Completed, $done->fresh()->status);
    }
}
