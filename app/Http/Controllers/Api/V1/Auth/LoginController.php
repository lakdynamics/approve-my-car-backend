<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Resources\V1\AuthTokensResource;
use App\Services\Auth\LoginService;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function store(LoginRequest $request, LoginService $login): AuthTokensResource
    {
        $tokens = $login->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
            $request->ip(),
            $request->userAgent(),
        );

        return new AuthTokensResource($tokens);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Signs out the current device only; other devices stay signed in.
     */
    public function destroy(Request $request, TokenService $tokens): Response
    {
        $tokens->revokeForAccessToken($request->user()->currentAccessToken());

        return response()->noContent();
    }
}
