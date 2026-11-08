<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who can see and act on a booking. Anyone else gets a 404, so the API
 * doesn't reveal that the booking exists.
 *
 * Admin routes don't use this: the "can:admin" route middleware lets
 * admins see every booking.
 */
class BookingPolicy
{
    /**
     * The customer's own view (GET /bookings/{id}).
     */
    public function view(User $user, Booking $booking): Response
    {
        return $this->allowIf($booking->user_id === $user->id);
    }

    public function cancel(User $user, Booking $booking): Response
    {
        return $this->allowIf($booking->user_id === $user->id);
    }

    /**
     * Inspectors can see open bookings (to decide whether to accept them)
     * and the ones they accepted. Admins can see any booking.
     */
    public function viewAsInspector(User $user, Booking $booking): Response
    {
        return $this->allowIf(
            $user->isAdmin()
            || ($user->isInspector()
                && ($booking->status === BookingStatus::Pending || $booking->inspector_id === $user->id))
        );
    }

    /**
     * Start and complete: only the inspector (or admin) who accepted it.
     */
    public function work(User $user, Booking $booking): Response
    {
        return $this->allowIf($user->canInspect() && $booking->inspector_id === $user->id);
    }

    private function allowIf(bool $allowed): Response
    {
        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
