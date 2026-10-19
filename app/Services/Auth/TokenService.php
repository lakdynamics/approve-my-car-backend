<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidRefreshTokenException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues, rotates and revokes access and refresh tokens.
 *
 * Access tokens are Sanctum personal access tokens. Refresh tokens live in
 * their own table, so one can never be accepted as an access token.
 */
class TokenService
{
    /**
     * Create an access token and a refresh token for one device.
     *
     * @param  string|null  $familyId  Continue an existing device session when rotating.
     */
    public function issue(User $user, string $deviceName, ?string $ipAddress = null, ?string $userAgent = null, ?string $familyId = null): IssuedTokens
    {
        return DB::transaction(function () use ($user, $deviceName, $ipAddress, $userAgent, $familyId) {
            $accessExpiresAt = now()->addMinutes(config('auth.tokens.access_token_minutes'));
            $refreshExpiresAt = now()->addDays(config('auth.tokens.refresh_token_days'));

            $accessToken = $user->createToken($deviceName, ['*'], $accessExpiresAt);

            $plainRefreshToken = Str::random(64);

            $user->refreshTokens()->create([
                'family_id' => $familyId ?? (string) Str::uuid(),
                'token_hash' => $this->hash($plainRefreshToken),
                'access_token_id' => $accessToken->accessToken->getKey(),
                'device_name' => $deviceName,
                'expires_at' => $refreshExpiresAt,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return new IssuedTokens(
                user: $user,
                accessToken: $accessToken->plainTextToken,
                accessTokenExpiresAt: $accessExpiresAt,
                refreshToken: $plainRefreshToken,
                refreshTokenExpiresAt: $refreshExpiresAt,
            );
        });
    }

    /**
     * Exchange a refresh token for a new token pair (rotation).
     *
     * Each refresh token works once. If a used one comes back, it has
     * probably been copied, so the whole device session is revoked.
     *
     * @throws InvalidRefreshTokenException
     */
    public function refresh(string $plainRefreshToken, ?string $ipAddress = null, ?string $userAgent = null): IssuedTokens
    {
        // The transaction returns null for any invalid token. We throw only
        // after it commits, so a reuse revocation is saved, not rolled back.
        $tokens = DB::transaction(function () use ($plainRefreshToken, $ipAddress, $userAgent): ?IssuedTokens {
            // Lock the row so two refreshes with the same token run one after the other.
            $refreshToken = RefreshToken::query()
                ->where('token_hash', $this->hash($plainRefreshToken))
                ->lockForUpdate()
                ->first();

            if ($refreshToken === null || $refreshToken->revoked_at !== null || $refreshToken->expires_at->isPast()) {
                return null;
            }

            if ($refreshToken->used_at !== null) {
                $this->revokeFamily($refreshToken->family_id);

                return null;
            }

            $refreshToken->forceFill(['used_at' => now()])->save();

            PersonalAccessToken::query()->whereKey($refreshToken->access_token_id)->delete();

            return $this->issue(
                $refreshToken->user,
                $refreshToken->device_name,
                $ipAddress,
                $userAgent,
                $refreshToken->family_id,
            );
        });

        return $tokens ?? throw new InvalidRefreshTokenException;
    }

    /**
     * Sign out one device: delete its access token and revoke its refresh tokens.
     */
    public function revokeForAccessToken(PersonalAccessToken $accessToken): void
    {
        DB::transaction(function () use ($accessToken) {
            $familyId = RefreshToken::query()
                ->where('access_token_id', $accessToken->getKey())
                ->value('family_id');

            if ($familyId !== null) {
                $this->revokeFamily($familyId);
            }

            $accessToken->delete();
        });
    }

    /**
     * Revoke every refresh token of one device session and delete its access tokens.
     */
    private function revokeFamily(string $familyId): void
    {
        $accessTokenIds = RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNotNull('access_token_id')
            ->pluck('access_token_id');

        PersonalAccessToken::query()->whereKey($accessTokenIds)->delete();

        RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
