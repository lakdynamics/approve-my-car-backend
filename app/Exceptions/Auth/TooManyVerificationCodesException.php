<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * A new code was requested too soon after the previous one,
 * or too many codes were requested for one email this hour.
 */
class TooManyVerificationCodesException extends Exception implements ShouldntReport
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Please wait before requesting another verification code.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'TOO_MANY_CODE_REQUESTS',
            'retry_after' => $this->retryAfterSeconds,
        ], 429, ['Retry-After' => $this->retryAfterSeconds]);
    }
}
