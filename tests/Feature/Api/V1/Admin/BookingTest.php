<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/bookings')->assertForbidden();

        Sanctum::actingAs(User::factory()->inspector()->create());
        $this->getJson('/api/v1/admin/bookings')->assertForbidden();
    }

    public function test_admin_lists_and_filters_bookings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $inspector = User::factory()->inspector()->create();

        $pending = Booking::factory()->create(['scheduled_date' => '2026-11-20']);
        $accepted = Booking::factory()->accepted($inspector)->create(['scheduled_date' => '2026-11-21']);
        Booking::factory()->cancelled()->create(['scheduled_date' => '2026-11-20']);

        $this->getJson('/api/v1/admin/bookings')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/admin/bookings?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->getJson('/api/v1/admin/bookings?date=2026-11-21')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $accepted->id);

        $this->getJson('/api/v1/admin/bookings?status=nope')->assertUnprocessable();
    }

    public function test_admin_views_any_booking_with_customer_and_inspector(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $inspector = User::factory()->inspector()->create();
        $booking = Booking::factory()->accepted($inspector)->create();

        $this->getJson("/api/v1/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.id', $booking->user_id)
            ->assertJsonPath('data.inspector.id', $inspector->id);
    }

    public function test_dashboard_counts_bookings_by_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $inspector = User::factory()->inspector()->create();

        Booking::factory()->count(2)->create();
        Booking::factory()->accepted($inspector)->create();
        Booking::factory()->completed($inspector)->create();
        Booking::factory()->cancelled()->create();

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.bookings.total', 5)
            ->assertJsonPath('data.bookings.by_status.pending', 2)
            ->assertJsonPath('data.bookings.by_status.accepted', 1)
            ->assertJsonPath('data.bookings.by_status.in_progress', 0)
            ->assertJsonPath('data.bookings.by_status.completed', 1)
            ->assertJsonPath('data.bookings.by_status.cancelled', 1);
    }
}
