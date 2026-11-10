<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Profile\DeleteAccountRequest;
use App\Http\Requests\V1\Profile\UpdatePasswordRequest;
use App\Http\Requests\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\Profile\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The signed-in user's own profile. GET /me lives in MeController.
 */
class ProfileController extends Controller
{
    public function __construct(private AccountService $account) {}

    /**
     * PATCH /api/v1/me
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user);
    }

    /**
     * PUT /api/v1/me/password
     *
     * Other devices are signed out; this one stays signed in.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        $this->account->changePassword(
            $request->user(),
            $token instanceof PersonalAccessToken ? $token : null,
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your password has been changed. Other devices have been signed out.',
        ]);
    }

    /**
     * DELETE /api/v1/me
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $this->account->delete($request->user());

        return response()->noContent();
    }
}
