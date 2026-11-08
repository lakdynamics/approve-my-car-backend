<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\LocationSource;
use App\Models\Booking;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Customer side: creating, listing, viewing and cancelling bookings.
 */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // 06:00 UTC = 11:30 in Colombo on 10 Nov. Tomorrow is 11 Nov.
        $this->travelTo('2026-11-10 06:00:00');

        $this->user = Sanctum::actingAs(User::factory()->create());
        $this->vehicle = Vehicle::factory()->for($this->user)->create();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/api/v1/bookings'],
            'create' => ['post', '/api/v1/bookings'],
            'view' => ['get', '/api/v1/bookings/1'],
            'cancel' => ['post', '/api/v1/bookings/1/cancel'],
        ];
    }

    #[DataProvider('routes')]
    public function test_guests_are_rejected(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();

        $this->json($method, $uri, [], ['Authorization' => ''])->assertUnauthorized();
    }

    public function test_booking_with_a_map_pin(): void
    {
        $this->postJson('/api/v1/bookings', $this->validData([
            'location_notes' => 'Blue gate',
            'notes' => 'Parked in the garage',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.scheduled_date', '2026-11-12')
            ->assertJsonPath('data.time_slot', 'morning')
            ->assertJsonPath('data.time_slot_label', '8:00 AM – 12:00 PM')
            ->assertJsonPath('data.location.source', 'map')
            ->assertJsonPath('data.location.latitude', 6.9271)
            ->assertJsonPath('data.location.longitude', 79.8612)
            ->assertJsonPath('data.location.address_text', '12 Galle Road, Colombo 03')
            ->assertJsonPath('data.location.notes', 'Blue gate')
            ->assertJsonPath('data.notes', 'Parked in the garage')
            ->assertJsonPath('data.vehicle.saved_vehicle_id', $this->vehicle->id)
            ->assertJsonPath('data.vehicle.make', $this->vehicle->make)
            ->assertJsonPath('data.vehicle.registration_number', $this->vehicle->registration_number)
            ->assertJsonPath('data.inspector', null)
            ->assertJsonMissingPath('data.customer');

        $booking = Booking::sole();
        $this->assertSame($this->user->id, $booking->user_id);
        $this->assertSame(BookingStatus::Pending, $booking->status);
    }

    public function test_booking_with_gps(): void
    {
        $this->postJson('/api/v1/bookings', $this->validData(['location_source' => 'gps']))
            ->assertCreated()
            ->assertJsonPath('data.location.source', 'gps');
    }

    public function test_booking_with_a_saved_address_copies_its_location(): void
    {
        $address = UserAddress::factory()->for($this->user)->default()->create([
            'line1' => '5 Temple Road',
            'line2' => null,
            'city' => 'Nuwara Eliya',
            'district' => 'nuwara_eliya',
            'latitude' => 6.9497,
            'longitude' => 80.7891,
        ]);

        $this->postJson('/api/v1/bookings', [
            'vehicle_id' => $this->vehicle->id,
            'scheduled_date' => '2026-11-12',
            'time_slot' => 'afternoon',
            'location_source' => 'saved_address',
            'address_id' => $address->id,
            // Ignored for a saved address.
            'latitude' => 1.0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.location.source', 'saved_address')
            ->assertJsonPath('data.location.latitude', 6.9497)
            ->assertJsonPath('data.location.longitude', 80.7891)
            ->assertJsonPath('data.location.address_text', '5 Temple Road, Nuwara Eliya, Nuwara Eliya');

        // Editing the address later doesn't change the booking.
        $address->update(['line1' => 'Somewhere else']);
        $this->assertSame('5 Temple Road, Nuwara Eliya, Nuwara Eliya', Booking::sole()->address_text);
        $this->assertSame(LocationSource::SavedAddress, Booking::sole()->location_source);
    }

    public function test_saved_address_without_a_map_pin_is_rejected(): void
    {
        $address = UserAddress::factory()->for($this->user)->default()->create();

        $this->postJson('/api/v1/bookings', [
            'vehicle_id' => $this->vehicle->id,
            'scheduled_date' => '2026-11-12',
            'time_slot' => 'morning',
            'location_source' => 'saved_address',
            'address_id' => $address->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id' => 'This address has no map location. Edit it to add a pin, or pick the location on the map.']);
    }

    public function test_someone_elses_vehicle_or_address_is_rejected(): void
    {
        $otherVehicle = Vehicle::factory()->create();
        $otherAddress = UserAddress::factory()->create(['latitude' => 6.9, 'longitude' => 79.9]);

        $this->postJson('/api/v1/bookings', $this->validData(['vehicle_id' => $otherVehicle->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id' => 'Select one of your vehicles.']);

        $this->postJson('/api/v1/bookings', [
            'vehicle_id' => $this->vehicle->id,
            'scheduled_date' => '2026-11-12',
            'time_slot' => 'morning',
            'location_source' => 'saved_address',
            'address_id' => $otherAddress->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id' => 'Select one of your saved addresses.']);
    }

    public function test_deleted_vehicle_is_rejected(): void
    {
        $this->vehicle->delete();

        $this->postJson('/api/v1/bookings', $this->validData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    }

    public function test_location_outside_sri_lanka_is_rejected(): void
    {
        // Latitude and longitude swapped.
        $this->postJson('/api/v1/bookings', $this->validData(['latitude' => 79.8612, 'longitude' => 6.9271]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude' => 'We only serve locations in Sri Lanka.',
                'longitude' => 'We only serve locations in Sri Lanka.',
            ]);
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->postJson('/api/v1/bookings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['make', 'model', 'year', 'vehicle_type', 'scheduled_date', 'time_slot', 'location_source']);

        $this->postJson('/api/v1/bookings', $this->validData([
            'latitude' => null,
            'longitude' => null,
            'address_text' => null,
            'time_slot' => 'midnight',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude', 'address_text', 'time_slot']);

        $this->postJson('/api/v1/bookings', $this->validData(['location_source' => 'saved_address']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address_id');
    }

    public function test_date_must_be_from_tomorrow_up_to_30_days(): void
    {
        $this->postJson('/api/v1/bookings', $this->validData(['scheduled_date' => '2026-11-10']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_date' => 'Choose a date from tomorrow onwards.']);

        $this->postJson('/api/v1/bookings', $this->validData(['scheduled_date' => '2026-12-11']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_date' => 'You can book up to 30 days ahead.']);

        $this->postJson('/api/v1/bookings', $this->validData(['scheduled_date' => '2026-11-11']))->assertCreated();

        $second = Vehicle::factory()->for($this->user)->create();
        $this->postJson('/api/v1/bookings', $this->validData(['vehicle_id' => $second->id, 'scheduled_date' => '2026-12-10']))->assertCreated();
    }

    public function test_dates_use_sri_lanka_time(): void
    {
        // 19:00 UTC on 10 Nov is already 00:30 on 11 Nov in Colombo,
        // so 11 Nov is "today" and can't be booked.
        $this->travelTo('2026-11-10 19:00:00');

        $this->postJson('/api/v1/bookings', $this->validData(['scheduled_date' => '2026-11-11']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_date');

        $this->postJson('/api/v1/bookings', $this->validData(['scheduled_date' => '2026-11-12']))->assertCreated();
    }

    public function test_same_vehicle_can_have_several_active_bookings(): void
    {
        $this->postJson('/api/v1/bookings', $this->validData())->assertCreated();
        $this->postJson('/api/v1/bookings', $this->validData())->assertCreated();

        $this->assertSame(2, Booking::count());
    }

    public function test_booking_with_typed_vehicle_details(): void
    {
        $toyota = VehicleMake::factory()->create(['name' => 'Toyota']);

        $this->postJson('/api/v1/bookings', $this->typedVehicleData([
            'make_id' => $toyota->id,
            'registration_number' => '  wp   cab 1234 ',
            'mileage' => 85000,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.vehicle', [
                'saved_vehicle_id' => null,
                'make_id' => $toyota->id,
                'make' => 'Toyota',
                'model' => 'Aqua',
                'year' => 2015,
                'vehicle_type' => 'car',
                'registration_number' => 'WP CAB 1234',
                'mileage' => 85000,
            ]);

        // Nothing is added to My vehicles.
        $this->assertSame(1, Vehicle::count());
    }

    public function test_typed_vehicle_without_registration_and_other_make(): void
    {
        $this->postJson('/api/v1/bookings', $this->typedVehicleData(['make' => 'Zastava']))
            ->assertCreated()
            ->assertJsonPath('data.vehicle.make_id', null)
            ->assertJsonPath('data.vehicle.make', 'Zastava')
            ->assertJsonPath('data.vehicle.registration_number', null);
    }

    public function test_typed_make_is_linked_to_the_list(): void
    {
        $honda = VehicleMake::factory()->create(['name' => 'Honda']);

        $this->postJson('/api/v1/bookings', $this->typedVehicleData(['make' => 'honda']))
            ->assertCreated()
            ->assertJsonPath('data.vehicle.make_id', $honda->id)
            ->assertJsonPath('data.vehicle.make', 'Honda');
    }

    public function test_typed_vehicle_details_are_validated(): void
    {
        $hidden = VehicleMake::factory()->hidden()->create();

        $this->postJson('/api/v1/bookings', $this->typedVehicleData([
            'make_id' => $hidden->id,
            'model' => null,
            'year' => 1900,
            'vehicle_type' => 'spaceship',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'make_id' => 'Select a make from the list.',
                'model', 'year', 'vehicle_type',
            ]);

        // Sending vehicle_id as null counts as "no saved vehicle".
        $this->postJson('/api/v1/bookings', $this->typedVehicleData(['vehicle_id' => null]))->assertCreated();
    }

    public function test_saved_vehicle_details_are_copied_and_typed_ones_ignored(): void
    {
        $this->postJson('/api/v1/bookings', $this->validData([
            'make' => 'Ignored',
            'model' => 'Ignored',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.vehicle.make', $this->vehicle->make)
            ->assertJsonPath('data.vehicle.model', $this->vehicle->model);

        // Editing the saved vehicle later doesn't change the booking.
        $model = $this->vehicle->model;
        $this->vehicle->update(['model' => 'Changed']);
        $this->assertSame($model, Booking::sole()->vehicle_model);
    }

    public function test_eleventh_booking_in_an_hour_is_refused(): void
    {
        $vehicles = Vehicle::factory()->for($this->user)->count(10)->create()->prepend($this->vehicle);

        foreach ($vehicles->take(10) as $vehicle) {
            $this->postJson('/api/v1/bookings', $this->validData(['vehicle_id' => $vehicle->id]))->assertCreated();
        }

        $this->postJson('/api/v1/bookings', $this->validData(['vehicle_id' => $vehicles->last()->id]))
            ->assertTooManyRequests();
    }

    public function test_list_shows_only_mine_and_filters(): void
    {
        $inspector = User::factory()->inspector()->create();
        $active = Booking::factory()->for($this->user)->for($this->vehicle)->accepted($inspector)->create(['scheduled_date' => '2026-11-15']);
        $sooner = Booking::factory()->for($this->user)->create(['scheduled_date' => '2026-11-12']);
        $done = Booking::factory()->for($this->user)->completed($inspector)->create(['scheduled_date' => '2026-11-01']);
        $cancelled = Booking::factory()->for($this->user)->cancelled()->create(['scheduled_date' => '2026-11-05']);
        Booking::factory()->create();

        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getJson('/api/v1/bookings?filter=active')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $active->id)
            ->assertJsonPath('data.1.inspector.id', $inspector->id)
            ->assertJsonPath('data.1.inspector.first_name', $inspector->first_name);

        $this->getJson('/api/v1/bookings?filter=past')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $cancelled->id)
            ->assertJsonPath('data.1.id', $done->id);

        $this->getJson('/api/v1/bookings?filter=nope')->assertUnprocessable();
    }

    public function test_view_my_booking_and_not_others(): void
    {
        $mine = Booking::factory()->for($this->user)->create();
        $theirs = Booking::factory()->create();

        $this->getJson("/api/v1/bookings/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);

        $this->getJson("/api/v1/bookings/{$theirs->id}")->assertNotFound();
        $this->postJson("/api/v1/bookings/{$theirs->id}/cancel", ['reason' => str_repeat('x', 900)])->assertNotFound();
    }

    public function test_cancel_pending_or_accepted(): void
    {
        $inspector = User::factory()->inspector()->create();
        $pending = Booking::factory()->for($this->user)->create();
        $accepted = Booking::factory()->for($this->user)->accepted($inspector)->create();

        $this->postJson("/api/v1/bookings/{$pending->id}/cancel", ['reason' => 'Sold the car'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Sold the car');

        $this->postJson("/api/v1/bookings/{$accepted->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', null);

        $this->assertNotNull($pending->fresh()->cancelled_at);
    }

    public function test_cannot_cancel_once_started_or_finished(): void
    {
        $inspector = User::factory()->inspector()->create();
        $started = Booking::factory()->for($this->user)->inProgress($inspector)->create();
        $done = Booking::factory()->for($this->user)->completed($inspector)->create();
        $cancelled = Booking::factory()->for($this->user)->cancelled()->create();

        foreach ([$started, $done, $cancelled] as $booking) {
            $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
                ->assertUnprocessable()
                ->assertJsonPath('code', 'BOOKING_NOT_CANCELLABLE');
        }

        $this->assertSame(BookingStatus::InProgress, $started->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $this->vehicle->id,
            'scheduled_date' => '2026-11-12',
            'time_slot' => 'morning',
            'location_source' => 'map',
            'latitude' => 6.9271,
            'longitude' => 79.8612,
            'address_text' => '12 Galle Road, Colombo 03',
        ], $overrides);
    }

    /**
     * A booking for a vehicle that isn't saved in My vehicles.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function typedVehicleData(array $overrides = []): array
    {
        $data = $this->validData([
            'make' => 'Toyota',
            'model' => 'Aqua',
            'year' => 2015,
            'vehicle_type' => 'car',
        ]);
        unset($data['vehicle_id']);

        return array_merge($data, $overrides);
    }
}
