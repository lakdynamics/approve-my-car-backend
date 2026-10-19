<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_correct_credentials_return_an_access_token_and_a_refresh_token(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'device_name' => 'Test phone',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'token_type',
                'access_token',
                'access_token_expires_at',
                'refresh_token',
                'refresh_token_expires_at',
                'user' => ['id', 'first_name', 'last_name', 'email', 'email_verified_at'],
            ]])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissingPath('data.user.password');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_refresh_token_is_stored_only_as_a_hash(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => $tokens['refresh_token']]);
        $this->assertDatabaseHas('refresh_tokens', ['token_hash' => hash('sha256', $tokens['refresh_token'])]);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_error(): void
    {
        $user = $this->createUser();

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword1',
            'device_name' => 'Test phone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => self::PASSWORD,
            'device_name' => 'Test phone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_sixth_attempt_in_a_minute_is_refused(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'WrongPassword1',
                'device_name' => 'Test phone',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'device_name' => 'Test phone',
        ])->assertStatus(429);
    }

    public function test_email_letter_case_does_not_matter(): void
    {
        $user = $this->createUser('nimal@example.com');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'Nimal@Example.com',
            'password' => self::PASSWORD,
            'device_name' => 'Test phone',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_device_name_is_required(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertJsonValidationErrors('device_name');
    }

    public function test_access_token_works_on_me(): void
    {
        $user = $this->createUser();
        $tokens = $this->signIn($user);

        $this->getMe($tokens['access_token'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_access_token_expires_after_thirty_minutes(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->travel(31)->minutes();

        $this->getMe($tokens['access_token'])->assertUnauthorized();
    }

    public function test_refresh_token_cannot_be_used_as_an_access_token(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->getMe($tokens['refresh_token'])->assertUnauthorized();
    }

    public function test_each_device_gets_its_own_tokens(): void
    {
        $user = $this->createUser();

        $phone = $this->signIn($user, 'Phone');
        $tablet = $this->signIn($user, 'Tablet');

        $this->assertNotSame($phone['access_token'], $tablet['access_token']);
        $this->assertSame(2, $user->tokens()->count());
        $this->assertSame(2, RefreshToken::distinct()->count('family_id'));
    }
}
