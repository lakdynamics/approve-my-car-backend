<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ListVehicleMakesRequest;
use App\Http\Requests\V1\Admin\StoreVehicleMakeRequest;
use App\Http\Requests\V1\Admin\UpdateVehicleMakeRequest;
use App\Http\Resources\V1\VehicleMakeResource;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Admins manage the makes list. There is no delete: hiding a make
 * (is_active = false) removes it from the dropdown, and vehicles that
 * already use it keep it.
 */
class VehicleMakeController extends Controller
{
    /**
     * GET /api/v1/admin/vehicle-makes?search=&include_inactive=1
     */
    public function index(ListVehicleMakesRequest $request): AnonymousResourceCollection
    {
        $search = $request->validated('search');

        $makes = VehicleMake::query()
            ->withCount('vehicles')
            ->unless($request->boolean('include_inactive'), fn ($query) => $query->active())
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return VehicleMakeResource::collection($makes);
    }

    /**
     * POST /api/v1/admin/vehicle-makes
     */
    public function store(StoreVehicleMakeRequest $request): JsonResponse
    {
        // refresh() picks up the database default for is_active.
        $make = VehicleMake::create($request->validated())->refresh();

        return (new VehicleMakeResource($make))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/admin/vehicle-makes/{vehicleMake}
     *
     * Renaming also updates the name shown on vehicles linked to it.
     */
    public function update(UpdateVehicleMakeRequest $request, VehicleMake $vehicleMake): VehicleMakeResource
    {
        DB::transaction(function () use ($request, $vehicleMake) {
            $vehicleMake->update($request->validated());

            if ($vehicleMake->wasChanged('name')) {
                Vehicle::withTrashed()
                    ->where('make_id', $vehicleMake->id)
                    ->update(['make' => $vehicleMake->name]);
            }
        });

        return new VehicleMakeResource($vehicleMake->loadCount('vehicles'));
    }
}
