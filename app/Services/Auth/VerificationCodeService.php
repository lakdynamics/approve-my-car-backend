<?php

namespace App\Services\Auth;

use App\Enums\VerificationCodePurpose;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Exceptions\Auth\TooManyVerificationCodesException;
use App\Models\EmailVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Creates, stores and checks the short numeric codes we email to users.
 *
 * The plain code exists only in memory and in the email. The database
 * keeps an HMAC of it, so a leaked database reveals no usable codes.
 */
class VerificationCodeService
{
    /**
     * Enforce the resend cooldown and the hourly limit for one email.
     *
     * Call this before every code request, including ones that will not
     * send a code, so the response time and status are the same whether
     * or not the email has an account.
     *
     * @throws TooManyVerificationCodesException
     */
    public function throttle(string $email, VerificationCodePurpose $purpose): void
    {
        $key = 'verification-code:'.$purpose->value.':'.sha1($email);
        $cooldownKey = $key.':cooldown';
        $hourlyKey = $key.':hourly';

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            throw new TooManyVerificationCodesException(RateLimiter::availableIn($cooldownKey));
        }

        if (RateLimiter::tooManyAttempts($hourlyKey, config('auth.verification_codes.max_per_hour'))) {
            throw new TooManyVerificationCodesException(RateLimiter::availableIn($hourlyKey));
        }

        RateLimiter::hit($cooldownKey, config('auth.verification_codes.resend_cooldown_seconds'));
        RateLimiter::hit($hourlyKey, 3600);
    }

    /**
     * Create a new code for the email, replacing any earlier unused code.
     *
     * @param  array<string, mixed>|null  $payload  Data to keep until the code is verified.
     * @return string The plain code, to be emailed and then forgotten.
     */
    public function issue(string $email, VerificationCodePurpose $purpose, ?array $payload = null, ?string $ipAddress = null): string
    {
        $code = $this->generate();

        DB::transaction(function () use ($email, $purpose, $payload, $ipAddress, $code) {
            // Only the newest code may work, so retire any older ones.
            EmailVerificationCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            EmailVerificationCode::create([
                'email' => $email,
                'purpose' => $purpose,
                'code_hash' => $this->hash($email, $purpose, $code),
                'payload' => $payload,
                'expires_at' => now()->addMinutes(config('auth.verification_codes.expire_minutes')),
                'ip_address' => $ipAddress,
            ]);
        });

        return $code;
    }

    /**
     * Check a code and mark it as used. A code can succeed only once.
     *
     * @throws InvalidVerificationCodeException
     */
    public function verify(string $email, VerificationCodePurpose $purpose, string $code): EmailVerificationCode
    {
        $record = EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($record === null || $record->attempts >= config('auth.verification_codes.max_attempts')) {
            throw new InvalidVerificationCodeException;
        }

        if (! hash_equals($record->code_hash, $this->hash($email, $purpose, $code))) {
            $record->increment('attempts');

            throw new InvalidVerificationCodeException;
        }

        // Conditional update: if two requests arrive with the same code at
        // the same moment, only one of them can change consumed_at.
        $consumed = EmailVerificationCode::query()
            ->whereKey($record->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($consumed === 0) {
            throw new InvalidVerificationCodeException;
        }

        return $record;
    }

    /**
     * The most recent code for the email, whether used or not.
     */
    public function latest(string $email, VerificationCodePurpose $purpose): ?EmailVerificationCode
    {
        return EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();
    }

    private function generate(): string
    {
        $length = config('auth.verification_codes.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Keyed hash of the code. Including the email and purpose means a
     * code's hash is only meaningful for that one email and purpose.
     */
    private function hash(string $email, VerificationCodePurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', $purpose->value.'|'.$email.'|'.$code, config('app.key'));
    }
}
