<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Vehicles\StoreVehicleRequest;
use App\Http\Requests\V1\Vehicles\UpdateVehicleRequest;
use App\Http\Resources\V1\VehicleResource;
use App\Models\Vehicle;
use App\Services\Vehicles\VehicleMakeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in user's own vehicles.
 */
class VehicleController extends Controller
{
    public function __construct(private VehicleMakeResolver $makes) {}

    /**
     * GET /api/v1/vehicles
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $vehicles = $request->user()->vehicles()
            ->latest()
            ->latest('id')
            ->paginate(20);

        return VehicleResource::collection($vehicles);
    }

    /**
     * POST /api/v1/vehicles
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->create($this->makes->resolve($request->validated()));

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/vehicles/{vehicle}
     */
    public function show(Vehicle $vehicle): VehicleResource
    {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }

    /**
     * PATCH /api/v1/vehicles/{vehicle}
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        // Ownership is checked in UpdateVehicleRequest::authorize().
        $vehicle->update($this->makes->resolve($request->validated()));

        return new VehicleResource($vehicle);
    }

    /**
     * DELETE /api/v1/vehicles/{vehicle}
     *
     * Hides the vehicle. The row is kept for history. Bookings keep their
     * own copy of the vehicle details, so they aren't affected.
     */
    public function destroy(Vehicle $vehicle): Response
    {
        Gate::authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->noContent();
    }
}
