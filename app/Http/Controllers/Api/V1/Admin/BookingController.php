<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Bookings\ListBookingsRequest;
use App\Http\Resources\V1\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only view of every booking for admins.
 */
class BookingController extends Controller
{
    /**
     * GET /api/v1/admin/bookings?status=&date=
     */
    public function index(ListBookingsRequest $request): AnonymousResourceCollection
    {
        $status = $request->validated('status');
        $date = $request->validated('date');

        $bookings = Booking::query()
            ->with(['user', 'inspector'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($date !== null, fn ($query) => $query->whereDate('scheduled_date', $date))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return BookingResource::collection($bookings);
    }

    /**
     * GET /api/v1/admin/bookings/{booking}
     */
    public function show(Booking $booking): BookingResource
    {
        return new BookingResource($booking->load(['user', 'inspector']));
    }
}
