<?php

namespace App\Exceptions\Admin;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * The type change isn't allowed for this user, for example promoting an
 * admin to inspector or demoting someone who isn't an inspector.
 */
class InvalidUserTypeException extends Exception implements ShouldntReport
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'INVALID_USER_TYPE',
        ], 422);
    }
}
