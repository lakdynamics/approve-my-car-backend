<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of a code when someone tries to register an email that
 * already has an account. The API response is the same in both cases.
 */
class AccountAlreadyExistsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You already have an account')
            ->line('Someone tried to create a new account with this email address, but an account already exists.')
            ->line('If this was you, please sign in to the app instead.')
            ->line('If it was not you, you can ignore this email. Your account has not been changed.');
    }
}
