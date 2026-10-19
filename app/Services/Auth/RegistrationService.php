<?php

namespace App\Services\Auth;

use App\Enums\VerificationCodePurpose;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Models\User;
use App\Notifications\Auth\AccountAlreadyExistsNotification;
use App\Notifications\Auth\RegistrationCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Sign-up in two stages: request a code, then confirm it.
 *
 * No user row exists until the emailed code is confirmed, so mistyped
 * or fake addresses never become accounts.
 */
class RegistrationService
{
    public function __construct(private VerificationCodeService $codes) {}

    /**
     * Start a registration by emailing a verification code.
     *
     * If the email already has an account, the owner is warned instead and
     * the caller sees the same response, so this cannot be used to find out
     * who has an account.
     *
     * @param  array{first_name: string, last_name: string, email: string, password: string}  $data
     */
    public function start(array $data, ?string $ipAddress = null): void
    {
        $email = $data['email'];

        $this->codes->throttle($email, VerificationCodePurpose::Register);

        if (User::where('email', $email)->exists()) {
            Notification::route('mail', $email)->notify(new AccountAlreadyExistsNotification);

            return;
        }

        $this->sendCode($email, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            // Hashed now, so the plain password is never stored anywhere.
            'password' => Hash::make($data['password']),
        ], $ipAddress);
    }

    /**
     * Send a fresh code for a pending registration.
     *
     * Silently does nothing when there is no pending registration, so the
     * response is the same for every email.
     */
    public function resend(string $email, ?string $ipAddress = null): void
    {
        $this->codes->throttle($email, VerificationCodePurpose::Register);

        $pending = $this->codes->latest($email, VerificationCodePurpose::Register);

        if ($pending === null || User::where('email', $email)->exists()) {
            return;
        }

        $this->sendCode($email, $pending->payload, $ipAddress);
    }

    /**
     * Confirm the code and create the account.
     *
     * @throws InvalidVerificationCodeException
     */
    public function complete(string $email, string $code): User
    {
        $record = $this->codes->verify($email, VerificationCodePurpose::Register, $code);

        $user = new User([
            'first_name' => $record->payload['first_name'],
            'last_name' => $record->payload['last_name'],
            'email' => $email,
            'password' => $record->payload['password'],
        ]);

        // Not fillable on purpose: only this code path may mark an email verified.
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendCode(string $email, array $payload, ?string $ipAddress): void
    {
        $code = $this->codes->issue($email, VerificationCodePurpose::Register, $payload, $ipAddress);
        Notification::route('mail', $email)->notify(
            new RegistrationCodeNotification($code, config('auth.verification_codes.expire_minutes'))
        );
    }
}
