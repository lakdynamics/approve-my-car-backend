<?php

namespace App\Http\Controllers\Api\V1\Inspector;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Bookings\ListBookingsRequest;
use App\Http\Resources\V1\BookingResource;
use App\Models\Booking;
use App\Services\Bookings\InspectorBookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The inspector's Bookings section. The "can:inspector" route middleware
 * already checked that the caller is an inspector or an admin.
 */
class BookingController extends Controller
{
    public function __construct(private InspectorBookingService $bookings) {}

    /**
     * GET /api/v1/inspector/bookings?scope=available|mine|history
     *
     * available: open bookings any inspector can accept.
     * mine:      bookings I accepted that aren't finished.
     * history:   bookings I completed.
     */
    public function index(ListBookingsRequest $request): AnonymousResourceCollection
    {
        $inspector = $request->user();
        $query = Booking::query()->with(['user', 'inspector']);

        match ($request->validated('scope') ?? 'available') {
            'available' => $query->where('status', BookingStatus::Pending)
                ->where('scheduled_date', '>=', Booking::localToday()->toDateString())
                ->soonestFirst(),
            'mine' => $query->where('inspector_id', $inspector->id)
                ->whereIn('status', [BookingStatus::Accepted, BookingStatus::InProgress])
                ->soonestFirst(),
            'history' => $query->where('inspector_id', $inspector->id)
                ->where('status', BookingStatus::Completed)
                ->orderByDesc('completed_at')
                ->orderByDesc('id'),
        };

        return BookingResource::collection($query->paginate(20)->withQueryString());
    }

    /**
     * GET /api/v1/inspector/bookings/{booking}
     */
    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('viewAsInspector', $booking);

        return $this->resource($booking);
    }

    /**
     * POST /api/v1/inspector/bookings/{booking}/accept
     */
    public function accept(Request $request, Booking $booking): BookingResource
    {
        return $this->resource($this->bookings->accept($booking, $request->user()));
    }

    /**
     * POST /api/v1/inspector/bookings/{booking}/start
     */
    public function start(Booking $booking): BookingResource
    {
        Gate::authorize('work', $booking);

        return $this->resource($this->bookings->start($booking));
    }

    /**
     * POST /api/v1/inspector/bookings/{booking}/complete
     */
    public function complete(Booking $booking): BookingResource
    {
        Gate::authorize('work', $booking);

        return $this->resource($this->bookings->complete($booking));
    }

    private function resource(Booking $booking): BookingResource
    {
        return new BookingResource($booking->load(['user', 'inspector']));
    }
}
