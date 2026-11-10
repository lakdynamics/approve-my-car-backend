<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\RegisterRequest;
use App\Http\Requests\V1\Auth\ResendCodeRequest;
use App\Http\Requests\V1\Auth\VerifyRegistrationRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * The same reply for new and existing emails, so the API never
     * reveals who has an account.
     */
    private const CODE_SENT_MESSAGE = 'Please check your email to continue your registration.';

    public function __construct(private RegistrationService $registration) {}

    /**
     * POST /api/v1/auth/register
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $this->registration->start($request->validated(), $request->ip());

        return response()->json(['message' => self::CODE_SENT_MESSAGE], 202);
    }

    /**
     * POST /api/v1/auth/register/verify
     */
    public function verify(VerifyRegistrationRequest $request): JsonResponse
    {
        $user = $this->registration->complete(
            $request->validated('email'),
            $request->validated('code'),
        );

        return (new UserResource($user))
            ->additional(['message' => 'Your account has been created. Please sign in.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/auth/register/resend
     */
    public function resend(ResendCodeRequest $request): JsonResponse
    {
        $this->registration->resend($request->validated('email'), $request->ip());

        return response()->json(['message' => self::CODE_SENT_MESSAGE], 202);
    }
}
