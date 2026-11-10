<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Profile\StoreAddressRequest;
use App\Http\Requests\V1\Profile\UpdateAddressRequest;
use App\Http\Resources\V1\AddressResource;
use App\Models\UserAddress;
use App\Services\Profile\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in user's saved addresses.
 */
class AddressController extends Controller
{
    public function __construct(private AddressService $addresses) {}

    /**
     * GET /api/v1/addresses
     *
     * Default address first, then newest. Not paginated: a user has at
     * most AddressService::MAX_ADDRESSES.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->latest('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    /**
     * POST /api/v1/addresses
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addresses->create(
            $request->user(),
            $request->safe()->except('is_default'),
            $request->boolean('is_default'),
        );

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/addresses/{address}
     */
    public function show(UserAddress $address): AddressResource
    {
        Gate::authorize('view', $address);

        return new AddressResource($address);
    }

    /**
     * PATCH /api/v1/addresses/{address}
     */
    public function update(UpdateAddressRequest $request, UserAddress $address): AddressResource
    {
        // Ownership is checked in UpdateAddressRequest::authorize().
        $address = $this->addresses->update(
            $address,
            $request->safe()->except('is_default'),
            $request->has('is_default') ? $request->boolean('is_default') : null,
        );

        return new AddressResource($address);
    }

    /**
     * DELETE /api/v1/addresses/{address}
     */
    public function destroy(UserAddress $address): Response
    {
        Gate::authorize('delete', $address);

        $this->addresses->delete($address);

        return response()->noContent();
    }
}
