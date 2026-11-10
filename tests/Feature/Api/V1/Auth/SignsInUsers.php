<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Helpers for tests that need a signed-in user.
 */
trait SignsInUsers
{
    protected const PASSWORD = 'Secret123';

    protected function createUser(string $email = 'nimal@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * Sign in through the API and return the "data" part of the response.
     *
     * @return array<string, mixed>
     */
    protected function signIn(User $user, string $deviceName = 'Test phone'): array
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'device_name' => $deviceName,
        ])->assertOk()->json('data');
    }

    /**
     * Call /me with the given access token.
     *
     * Laravel keeps the signed-in user in memory between requests made in
     * one test, so the guards are reset first to make each request check
     * its token from scratch, as a real request would.
     */
    protected function getMe(string $accessToken): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$accessToken]);
    }

    protected function refresh(string $refreshToken): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken]);
    }
}
