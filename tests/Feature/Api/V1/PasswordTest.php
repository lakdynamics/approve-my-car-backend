<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\V1\Auth\SignsInUsers;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private const NEW_PASSWORD = 'NewSecret456';

    public function test_password_change_keeps_this_device_and_signs_out_others(): void
    {
        $user = $this->createUser();
        $phone = $this->signIn($user, 'Phone');
        $tablet = $this->signIn($user, 'Tablet');

        $this->changePassword($phone['access_token'], [
            'current_password' => self::PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been changed. Other devices have been signed out.');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));

        // This device keeps working, including refreshing.
        $this->getMe($phone['access_token'])->assertOk();
        $this->refresh($phone['refresh_token'])->assertOk();

        // The other device is signed out.
        $this->getMe($tablet['access_token'])->assertUnauthorized();
        $this->refresh($tablet['refresh_token'])->assertUnauthorized();

        // The new password works for signing in.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'device_name' => 'Laptop',
        ])->assertOk();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->createUser();
        $tokens = $this->signIn($user);

        $this->changePassword($tokens['access_token'], [
            'current_password' => 'WrongPassword1',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password' => 'Your current password is incorrect.']);

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_new_password_must_differ_be_confirmed_and_be_strong(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->changePassword($tokens['access_token'], [
            'current_password' => self::PASSWORD,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->changePassword($tokens['access_token'], [
            'current_password' => self::PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'Mismatch123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->changePassword($tokens['access_token'], [
            'current_password' => self::PASSWORD,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_sixth_attempt_in_a_minute_is_refused(): void
    {
        $tokens = $this->signIn($this->createUser());

        for ($i = 0; $i < 5; $i++) {
            $this->changePassword($tokens['access_token'], [
                'current_password' => 'WrongPassword1',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])->assertUnprocessable();
        }

        $this->changePassword($tokens['access_token'], [
            'current_password' => self::PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertTooManyRequests();
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->putJson('/api/v1/me/password', [])->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changePassword(string $accessToken, array $data): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->putJson('/api/v1/me/password', $data, ['Authorization' => 'Bearer '.$accessToken]);
    }
}
