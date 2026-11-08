<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ListUsersRequest;
use App\Http\Requests\V1\Admin\StoreInspectorRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Admin\InspectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InspectorController extends Controller
{
    public function __construct(private InspectorService $inspectors) {}

    /**
     * GET /api/v1/admin/inspectors?search=
     */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $inspectors = User::query()
            ->where('type', UserType::Inspector)
            ->search($request->validated('search'))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($inspectors);
    }

    /**
     * POST /api/v1/admin/inspectors
     *
     * Create a new inspector account.
     */
    public function store(StoreInspectorRequest $request): JsonResponse
    {
        $user = $this->inspectors->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/admin/inspectors/{user}
     *
     * Make an existing user an inspector.
     */
    public function update(User $user): UserResource
    {
        return new UserResource($this->inspectors->promote($user));
    }

    /**
     * DELETE /api/v1/admin/inspectors/{user}
     *
     * Turn an inspector back into a normal user. The account is kept.
     */
    public function destroy(User $user): UserResource
    {
        return new UserResource($this->inspectors->demote($user));
    }
}
