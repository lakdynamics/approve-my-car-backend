<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\VehicleMakeResource;
use App\Models\VehicleMake;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleMakeController extends Controller
{
    /**
     * GET /api/v1/vehicle-makes
     *
     * Every active make, A to Z, for the Add Vehicle dropdown. Not
     * paginated: the list is short. The app adds "Other" itself.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return VehicleMakeResource::collection(
            VehicleMake::query()->active()->orderBy('name')->get()
        );
    }
}
