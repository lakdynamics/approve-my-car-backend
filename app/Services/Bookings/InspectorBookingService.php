<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\Bookings\BookingAlreadyTakenException;
use App\Exceptions\Bookings\InvalidBookingStatusException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notifications\BookingNotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Inspector actions: accept a booking, start it, complete it.
 */
class InspectorBookingService
{
    public function __construct(private BookingNotificationService $notifications) {}

    /**
     * Claim a pending booking. Only one inspector can win: the update only
     * succeeds while the booking is still pending.
     *
     * @throws BookingAlreadyTakenException When another inspector accepted it first.
     * @throws InvalidBookingStatusException When it was cancelled or its date has passed.
     */
    public function accept(Booking $booking, User $inspector): Booking
    {
        return DB::transaction(function () use ($booking, $inspector): Booking {
            if ($booking->scheduled_date->toDateString() < Booking::localToday()->toDateString()) {
                throw new InvalidBookingStatusException("This booking's date has passed.");
            }

            $claimed = Booking::query()
                ->whereKey($booking->id)
                ->where('status', BookingStatus::Pending)
                ->update([
                    'status' => BookingStatus::Accepted->value,
                    'inspector_id' => $inspector->id,
                    'accepted_at' => now(),
                    'updated_at' => now(),
                ]);

            $booking->refresh();

            if ($claimed === 1) {
                $this->notifications->statusChanged($booking);

                return $booking;
            }

            if ($booking->status === BookingStatus::Cancelled) {
                throw new InvalidBookingStatusException('This booking was cancelled by the customer.');
            }

            if ($booking->inspector_id === $inspector->id) {
                // Already accepted by this inspector, e.g. a double tap.
                return $booking;
            }

            throw new BookingAlreadyTakenException;
        });
    }

    /**
     * @throws InvalidBookingStatusException
     */
    public function start(Booking $booking): Booking
    {
        return $this->move($booking, BookingStatus::Accepted, function (Booking $booking) {
            if ($booking->scheduled_date->toDateString() > Booking::localToday()->toDateString()) {
                throw new InvalidBookingStatusException('You can start this inspection on its scheduled date.');
            }

            $booking->forceFill([
                'status' => BookingStatus::InProgress,
                'started_at' => now(),
            ]);
        });
    }

    /**
     * @throws InvalidBookingStatusException
     */
    public function complete(Booking $booking): Booking
    {
        return $this->move($booking, BookingStatus::InProgress, function (Booking $booking) {
            $booking->forceFill([
                'status' => BookingStatus::Completed,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Change status under a row lock, so a customer cancelling at the same
     * moment can't be overwritten.
     *
     * @param  callable(Booking): void  $change
     */
    private function move(Booking $booking, BookingStatus $from, callable $change): Booking
    {
        return DB::transaction(function () use ($booking, $from, $change) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== $from) {
                throw new InvalidBookingStatusException(match ($booking->status) {
                    BookingStatus::Cancelled => 'This booking was cancelled by the customer.',
                    BookingStatus::Completed => 'This inspection is already completed.',
                    default => 'This action is not allowed for a booking that is '.str_replace('_', ' ', $booking->status->value).'.',
                });
            }

            $change($booking);
            $booking->save();

            $this->notifications->statusChanged($booking);

            return $booking;
        });
    }
}
