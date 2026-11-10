<?php

namespace App\Exceptions\Bookings;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * Another inspector accepted the booking first.
 */
class BookingAlreadyTakenException extends Exception implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Another inspector has already accepted this booking.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'BOOKING_ALREADY_TAKEN',
        ], 409);
    }
}
