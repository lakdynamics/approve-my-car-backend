<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Bookings\InspectorBookingService;
use App\Services\Profile\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_booking_notifies_only_active_inspectors(): void
    {
        $customer = Sanctum::actingAs(User::factory()->create());
        $inspectors = User::factory()->inspector()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $otherCustomer = User::factory()->create();
        $deleted = User::factory()->inspector()->create();
        $deleted->delete();
        $vehicle = Vehicle::factory()->for($customer)->create();

        $id = $this->postJson('/api/v1/bookings', [
            'vehicle_id' => $vehicle->id,
            'scheduled_date' => Booking::localToday()->addDays(2)->toDateString(),
            'time_slot' => 'morning', 'location_source' => 'map',
            'latitude' => 6.9271, 'longitude' => 79.8612, 'address_text' => 'Colombo',
        ])->assertCreated()->json('data.id');

        foreach ($inspectors as $inspector) {
            $notification = $inspector->notifications()->sole();
            $this->assertSame('booking.created', $notification->type);
            $this->assertSame($id, $notification->data['booking_id']);
        }
        foreach ([$admin, $customer, $otherCustomer, $deleted] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_status_changes_notify_customer_once_and_rejected_actions_do_not_notify(): void
    {
        $inspector = Sanctum::actingAs(User::factory()->inspector()->create());
        $booking = Booking::factory()->create(['scheduled_date' => Booking::localToday()]);
        $base = '/api/v1/inspector/bookings/'.$booking->id;

        $this->postJson($base.'/accept')->assertOk();
        $this->postJson($base.'/accept')->assertOk();
        $this->assertDatabaseCount('notifications', 1);

        $this->postJson($base.'/start')->assertOk();
        $this->postJson($base.'/start')->assertUnprocessable();
        $this->postJson($base.'/complete')->assertOk();
        $this->postJson($base.'/complete')->assertUnprocessable();

        $this->assertSame(
            ['booking.accepted', 'booking.completed', 'booking.started'],
            $booking->user->notifications()->orderBy('type')->pluck('type')->all()
        );
        $this->assertSame(0, $inspector->notifications()->count());
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_cancellation_notifies_assigned_inspector_only(): void
    {
        $customer = Sanctum::actingAs(User::factory()->create());
        $inspector = User::factory()->inspector()->create();
        $booking = Booking::factory()->for($customer)->accepted($inspector)->create();
        $pending = Booking::factory()->for($customer)->create();

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();
        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertUnprocessable();
        $this->postJson("/api/v1/bookings/{$pending->id}/cancel")->assertOk();

        $this->assertSame('booking.cancelled', $inspector->notifications()->sole()->type);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notifications_roll_back_with_the_booking_change(): void
    {
        $booking = Booking::factory()->create();
        $inspector = User::factory()->inspector()->create();
        DB::beginTransaction();
        app(InspectorBookingService::class)->accept($booking, $inspector);
        $this->assertDatabaseCount('notifications', 1);
        DB::rollBack();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_account_deletion_notifies_inspector_and_removes_private_notification_data(): void
    {
        $customer = User::factory()->create();
        $inspector = User::factory()->inspector()->create();
        $booking = Booking::factory()->for($customer)->create();
        app(InspectorBookingService::class)->accept($booking, $inspector);
        DeviceToken::factory()->for($customer)->create();

        app(AccountService::class)->delete($customer);

        $this->assertSame('booking.cancelled', $inspector->notifications()->sole()->type);
        $this->assertSame(0, $customer->notifications()->count());
        $this->assertSame(0, $customer->deviceTokens()->count());
    }
}
