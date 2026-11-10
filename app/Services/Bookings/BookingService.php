<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Enums\LocationSource;
use App\Exceptions\Bookings\InvalidBookingStatusException;
use App\Models\Booking;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Vehicle;
use App\Services\Vehicles\VehicleMakeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer actions: book an inspection and cancel it.
 */
class BookingService
{
    public function __construct(private VehicleMakeResolver $makes) {}

    /**
     * Create a booking from already-validated input (StoreBookingRequest).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When the saved address has no map location.
     */
    public function create(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data) {
            $source = LocationSource::from($data['location_source']);
            $location = $source === LocationSource::SavedAddress
                ? $this->locationFromAddress(UserAddress::findOrFail($data['address_id']))
                : [
                    'address_id' => null,
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'address_text' => $data['address_text'],
                ];

            $booking = new Booking;
            $booking->forceFill([
                'user_id' => $user->id,
                ...$this->vehicleDetails($data),
                'status' => BookingStatus::Pending,
                'scheduled_date' => $data['scheduled_date'],
                'time_slot' => $data['time_slot'],
                'location_source' => $source,
                ...$location,
                'location_notes' => $data['location_notes'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            return $booking;
        });
    }

    /**
     * Cancel a booking before the inspection starts.
     *
     * @throws InvalidBookingStatusException When it has started, finished or is already cancelled.
     */
    public function cancel(Booking $booking, ?string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {
            // Re-read under a lock, in case an inspector is starting it right now.
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->status->isCancellableByUser()) {
                throw new InvalidBookingStatusException(
                    'This booking can no longer be cancelled.',
                    'BOOKING_NOT_CANCELLABLE',
                );
            }

            $booking->forceFill([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $booking;
        });
    }

    /**
     * The vehicle details the booking keeps: copied from a saved vehicle,
     * or the typed-in ones (with the make linked to the list if it matches).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function vehicleDetails(array $data): array
    {
        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);

            return [
                'vehicle_id' => $vehicle->id,
                'vehicle_make_id' => $vehicle->make_id,
                'vehicle_make' => $vehicle->make,
                'vehicle_model' => $vehicle->model,
                'vehicle_year' => $vehicle->year,
                'vehicle_type' => $vehicle->vehicle_type,
                'vehicle_registration' => $vehicle->registration_number,
                'vehicle_mileage' => $vehicle->mileage,
            ];
        }

        $typed = $this->makes->resolve($data);

        return [
            'vehicle_id' => null,
            'vehicle_make_id' => $typed['make_id'] ?? null,
            'vehicle_make' => $typed['make'],
            'vehicle_model' => $typed['model'],
            'vehicle_year' => $typed['year'],
            'vehicle_type' => $typed['vehicle_type'],
            'vehicle_registration' => $typed['registration_number'] ?? null,
            'vehicle_mileage' => $typed['mileage'] ?? null,
        ];
    }

    /**
     * Copy a saved address onto the booking.
     *
     * @return array{address_id: int, latitude: float, longitude: float, address_text: string}
     *
     * @throws ValidationException When the address has no map pin.
     */
    private function locationFromAddress(UserAddress $address): array
    {
        if ($address->latitude === null || $address->longitude === null) {
            throw ValidationException::withMessages([
                'address_id' => ['This address has no map location. Edit it to add a pin, or pick the location on the map.'],
            ]);
        }

        $text = implode(', ', array_filter([
            $address->line1,
            $address->line2,
            $address->city,
            $address->district->label(),
        ]));

        return [
            'address_id' => $address->id,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'address_text' => mb_substr($text, 0, 255),
        ];
    }
}
