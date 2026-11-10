<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\RefreshTokenRequest;
use App\Http\Resources\V1\AuthTokensResource;
use App\Services\Auth\TokenService;

class RefreshTokenController extends Controller
{
    /**
     * POST /api/v1/auth/refresh
     */
    public function store(RefreshTokenRequest $request, TokenService $tokens): AuthTokensResource
    {
        return new AuthTokensResource($tokens->refresh(
            $request->validated('refresh_token'),
            $request->ip(),
            $request->userAgent(),
        ));
    }
}
