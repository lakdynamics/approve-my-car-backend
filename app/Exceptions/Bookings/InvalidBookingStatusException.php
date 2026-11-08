<?php

namespace App\Exceptions\Bookings;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * The booking isn't in a state that allows this action, for example
 * starting a booking nobody has accepted, or cancelling one that's done.
 */
class InvalidBookingStatusException extends Exception implements ShouldntReport
{
    public function __construct(string $message, private string $errorCode = 'INVALID_BOOKING_STATUS')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], 422);
    }
}
