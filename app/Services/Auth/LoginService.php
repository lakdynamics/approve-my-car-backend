<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Signs a user in with email and password.
 */
class LoginService
{
    public function __construct(private TokenService $tokens) {}

    /**
     * @throws ValidationException When the email or password is wrong.
     */
    public function login(string $email, string $password, string $deviceName, ?string $ipAddress = null, ?string $userAgent = null): IssuedTokens
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            // Spend the same time as a real password check, so a quick reply
            // does not reveal that the email has no account.
            Hash::make($password);

            throw $this->failed();
        }

        if (! Hash::check($password, $user->password)) {
            throw $this->failed();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokens->issue($user, $deviceName, $ipAddress, $userAgent);
    }

    /**
     * One message for both a wrong password and an unknown email.
     */
    private function failed(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }
}
