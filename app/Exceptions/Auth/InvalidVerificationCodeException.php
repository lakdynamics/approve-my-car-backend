<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * The code is wrong, expired, already used, replaced by a newer one,
 * or has had too many wrong attempts. The reason is deliberately not
 * revealed, so the response gives an attacker nothing to work with.
 */
class InvalidVerificationCodeException extends Exception implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The verification code is invalid or has expired.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'INVALID_CODE',
            'errors' => ['code' => [$this->getMessage()]],
        ], 422);
    }
}
