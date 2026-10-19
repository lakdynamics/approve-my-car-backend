<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * The refresh token is unknown, expired, revoked or was already used.
 * The app should send the user back to the sign-in screen.
 */
class InvalidRefreshTokenException extends Exception implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The refresh token is invalid or has expired. Please sign in again.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'INVALID_REFRESH_TOKEN',
        ], 401);
    }
}
