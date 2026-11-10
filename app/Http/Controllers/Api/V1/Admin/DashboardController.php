<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\UserType;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

/**
 * Numbers for the admin home screen. Deleted users and vehicles are
 * never counted.
 */
class DashboardController extends Controller
{
    /**
     * GET /api/v1/admin/dashboard
     */
    public function __invoke(): JsonResponse
    {
        $vehiclesByType = $this->vehiclesByType();
        $bookingsByStatus = $this->bookingsByStatus();

        return response()->json([
            'data' => [
                'users' => [
                    'total' => User::where('type', UserType::User)->count(),
                ],
                'inspectors' => [
                    'total' => User::where('type', UserType::Inspector)->count(),
                ],
                'vehicles' => [
                    'total' => array_sum($vehiclesByType),
                    'by_type' => $vehiclesByType,
                ],
                'bookings' => [
                    'total' => array_sum($bookingsByStatus),
                    'by_status' => $bookingsByStatus,
                ],
            ],
        ]);
    }

    /**
     * Booking counts for every status, with 0 for statuses that have none.
     *
     * @return array<string, int>
     */
    private function bookingsByStatus(): array
    {
        $counts = Booking::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];

        foreach (BookingStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $byStatus;
    }

    /**
     * Vehicle counts for every type, with 0 for types that have none,
     * so the app always gets the same keys.
     *
     * @return array<string, int>
     */
    private function vehiclesByType(): array
    {
        $counts = Vehicle::query()
            ->toBase()
            ->selectRaw('vehicle_type, count(*) as total')
            ->groupBy('vehicle_type')
            ->pluck('total', 'vehicle_type');

        $byType = [];

        foreach (VehicleType::cases() as $type) {
            $byType[$type->value] = (int) ($counts[$type->value] ?? 0);
        }

        return $byType;
    }
}
