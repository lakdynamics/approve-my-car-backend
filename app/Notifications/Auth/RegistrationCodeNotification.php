<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the code that completes a registration.
 *
 * Queued, so the request does not wait for the mail server, and encrypted
 * while it waits in the queue, because it contains the plain code.
 */
class RegistrationCodeNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

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
            ->subject('Your verification code')
            ->line('Use this code to finish creating your account:')
            ->line('**'.$this->code.'**')
            ->line("The code expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not try to create an account, you can ignore this email.');
    }
}
