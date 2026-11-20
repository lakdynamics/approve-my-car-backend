<?php

namespace App\Http\Controllers\Api\V1\Inspector;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Inspector\SaveAnswersRequest;
use App\Http\Requests\V1\Inspector\UpdateSectionCommentRequest;
use App\Http\Resources\V1\InspectionResource;
use App\Http\Resources\V1\InspectionSectionResource;
use App\Models\Booking;
use App\Models\Inspection;
use App\Models\InspectionSection;
use App\Services\Inspections\InspectionService;
use Illuminate\Support\Facades\Gate;

/**
 * The inspector fills in the checklist of a started booking. The copy of
 * the checklist is made when the booking starts; completing the booking
 * locks it.
 */
class InspectionController extends Controller
{
    public function __construct(private InspectionService $inspections) {}

    /**
     * GET /api/v1/inspector/bookings/{booking}/inspection
     */
    public function show(Booking $booking): InspectionResource
    {
        Gate::authorize('work', $booking);

        return $this->resource($this->inspections->forBooking($booking));
    }

    /**
     * PATCH /api/v1/inspector/bookings/{booking}/inspection/answers
     *
     * Returns the whole inspection, so the app gets new outcomes and progress.
     */
    public function saveAnswers(SaveAnswersRequest $request, Booking $booking): InspectionResource
    {
        $inspection = $request->inspection();
        $this->inspections->saveAnswers($inspection, $request->validated('answers'));

        return $this->resource($inspection);
    }

    /**
     * PATCH /api/v1/inspector/bookings/{booking}/inspection/sections/{inspectionSection}
     */
    public function updateSection(UpdateSectionCommentRequest $request, Booking $booking, InspectionSection $inspectionSection): InspectionSectionResource
    {
        abort_unless($inspectionSection->inspection->booking_id === $booking->id, 404);

        $section = $this->inspections->saveComment($inspectionSection, $request->validated('comment'));

        return new InspectionSectionResource($section->load('answers'));
    }

    private function resource(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($inspection->load('sections.answers'));
    }
}
