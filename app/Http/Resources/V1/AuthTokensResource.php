<?php

namespace App\Http\Resources\V1;

use App\Services\Auth\IssuedTokens;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The response to signing in and refreshing: both tokens, when they
 * expire, and the signed-in user.
 *
 * @mixin IssuedTokens
 */
class AuthTokensResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $this->accessToken,
            'access_token_expires_at' => $this->accessTokenExpiresAt,
            'refresh_token' => $this->refreshToken,
            'refresh_token_expires_at' => $this->refreshTokenExpiresAt,
            'user' => new UserResource($this->user),
        ];
    }
}
