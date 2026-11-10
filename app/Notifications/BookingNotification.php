<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class BookingNotification extends Notification
{
    /** @param array{event: string, title: string, body: string, booking_id: int, status: string} $data */
    public function __construct(public array $data) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(User $notifiable): string
    {
        return $this->data['event'];
    }

    /** @return array{event: string, title: string, body: string, booking_id: int, status: string} */
    public function toDatabase(User $notifiable): array
    {
        return $this->data;
    }
}
