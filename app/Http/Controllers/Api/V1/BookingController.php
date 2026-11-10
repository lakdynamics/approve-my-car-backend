<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Bookings\CancelBookingRequest;
use App\Http\Requests\V1\Bookings\ListBookingsRequest;
use App\Http\Requests\V1\Bookings\StoreBookingRequest;
use App\Http\Resources\V1\BookingResource;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in customer's own bookings.
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    /**
     * GET /api/v1/bookings?filter=active|past
     */
    public function index(ListBookingsRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->bookings()->with(['inspector']);

        match ($request->validated('filter')) {
            'active' => $query->active()->soonestFirst(),
            'past' => $query->whereIn('status', [BookingStatus::Completed, BookingStatus::Cancelled])
                ->orderByDesc('scheduled_date')
                ->orderByDesc('id'),
            default => $query->latest()->latest('id'),
        };

        return BookingResource::collection($query->paginate(20)->withQueryString());
    }

    /**
     * POST /api/v1/bookings
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->user(), $request->validated());

        return (new BookingResource($booking->load(['inspector'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/bookings/{booking}
     */
    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('view', $booking);

        return new BookingResource($booking->load(['inspector']));
    }

    /**
     * POST /api/v1/bookings/{booking}/cancel
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): BookingResource
    {
        // Ownership is checked in CancelBookingRequest::authorize().
        $booking = $this->bookings->cancel($booking, $request->validated('reason'));

        return new BookingResource($booking->load(['inspector']));
    }
}
