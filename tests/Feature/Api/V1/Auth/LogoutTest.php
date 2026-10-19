<?php

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_logout_revokes_both_tokens_of_this_device(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$tokens['access_token']])
            ->assertNoContent();

        $this->getMe($tokens['access_token'])->assertUnauthorized();
        $this->refresh($tokens['refresh_token'])->assertUnauthorized();
    }

    public function test_logout_leaves_other_devices_signed_in(): void
    {
        $user = $this->createUser();
        $phone = $this->signIn($user, 'Phone');
        $tablet = $this->signIn($user, 'Tablet');

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$phone['access_token']])
            ->assertNoContent();

        $this->getMe($tablet['access_token'])->assertOk();
        $this->refresh($tablet['refresh_token'])->assertOk();
    }

    public function test_logout_requires_a_token(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
