<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\LocationSource;
use App\Enums\TimeSlot;
use App\Enums\VehicleType;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // A vehicle owned by the same customer, with its details copied
            // onto the booking (as BookingService does).
            'vehicle_id' => fn (array $attributes) => Vehicle::factory()->create(['user_id' => $attributes['user_id']])->id,
            'vehicle_make_id' => fn (array $attributes) => $this->savedVehicle($attributes)?->make_id,
            'vehicle_make' => fn (array $attributes) => $this->savedVehicle($attributes)->make ?? 'Toyota',
            'vehicle_model' => fn (array $attributes) => $this->savedVehicle($attributes)->model ?? 'Aqua',
            'vehicle_year' => fn (array $attributes) => $this->savedVehicle($attributes)->year ?? 2015,
            'vehicle_type' => fn (array $attributes) => $this->savedVehicle($attributes)->vehicle_type ?? VehicleType::Car,
            'vehicle_registration' => fn (array $attributes) => $this->savedVehicle($attributes)?->registration_number,
            'vehicle_mileage' => fn (array $attributes) => $this->savedVehicle($attributes)?->mileage,
            'inspector_id' => null,
            'status' => BookingStatus::Pending,
            'scheduled_date' => Booking::localToday()->addDays(3)->toDateString(),
            'time_slot' => TimeSlot::Morning,
            'location_source' => LocationSource::Map,
            'address_id' => null,
            'latitude' => 6.9271,
            'longitude' => 79.8612,
            'address_text' => '12 Galle Road, Colombo 03',
            'location_notes' => null,
            'notes' => null,
        ];
    }

    /**
     * Details typed into the booking, no saved vehicle.
     */
    public function withoutSavedVehicle(): static
    {
        return $this->state([
            'vehicle_id' => null,
            'vehicle_make_id' => null,
            'vehicle_make' => 'Honda',
            'vehicle_model' => 'Vezel',
            'vehicle_year' => 2018,
            'vehicle_type' => VehicleType::Suv,
            'vehicle_registration' => null,
            'vehicle_mileage' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function savedVehicle(array $attributes): ?Vehicle
    {
        return isset($attributes['vehicle_id']) ? Vehicle::withTrashed()->find($attributes['vehicle_id']) : null;
    }

    public function accepted(User $inspector): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Accepted,
            'inspector_id' => $inspector->id,
            'accepted_at' => now(),
        ]);
    }

    public function inProgress(User $inspector): static
    {
        return $this->accepted($inspector)->state(fn (array $attributes) => [
            'status' => BookingStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(User $inspector): static
    {
        return $this->inProgress($inspector)->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
