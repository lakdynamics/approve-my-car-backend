<?php

namespace App\Services\Notifications;

use App\Enums\BookingStatus;
use App\Enums\UserType;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingNotification;

class BookingNotificationService
{
    public function created(Booking $booking): void
    {
        User::query()->where('type', UserType::Inspector)->whereKeyNot($booking->user_id)
            ->chunkById(100, function ($inspectors) use ($booking): void {
                foreach ($inspectors as $inspector) {
                    $this->send($inspector, $booking, 'booking.created', 'New inspection booking', 'A new inspection booking is available.');
                }
            });
    }

    public function statusChanged(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Cancelled) {
            $this->send($booking->inspector, $booking, 'booking.cancelled', 'Booking cancelled', 'An inspection assigned to you was cancelled.');

            return;
        }

        [$event, $title, $body] = match ($booking->status) {
            BookingStatus::Accepted => ['booking.accepted', 'Booking accepted', 'An inspector has accepted your booking.'],
            BookingStatus::InProgress => ['booking.started', 'Inspection started', 'Your vehicle inspection has started.'],
            BookingStatus::Completed => ['booking.completed', 'Inspection completed', 'Your vehicle inspection is complete.'],
            default => [null, null, null],
        };

        if ($event !== null) {
            $this->send($booking->user, $booking, $event, $title, $body);
        }
    }

    private function send(?User $recipient, Booking $booking, string $event, string $title, string $body): void
    {
        if ($recipient === null || $recipient->trashed()) {
            return;
        }

        $recipient->notify(new BookingNotification([
            'event' => $event,
            'title' => $title,
            'body' => $body,
            'booking_id' => $booking->id,
            'status' => $booking->status->value,
        ]));
    }
}
