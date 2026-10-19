<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The token pair returned after signing in or refreshing.
 *
 * Holds the plain token strings, which exist only for this one response.
 */
final readonly class IssuedTokens
{
    public function __construct(
        public User $user,
        public string $accessToken,
        public CarbonInterface $accessTokenExpiresAt,
        public string $refreshToken,
        public CarbonInterface $refreshTokenExpiresAt,
    ) {}
}
