<?php

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_refresh_returns_a_new_pair_and_retires_the_old_access_token(): void
    {
        $old = $this->signIn($this->createUser());

        $new = $this->refresh($old['refresh_token'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->json('data');

        $this->assertNotSame($old['access_token'], $new['access_token']);
        $this->assertNotSame($old['refresh_token'], $new['refresh_token']);

        $this->getMe($old['access_token'])->assertUnauthorized();
        $this->getMe($new['access_token'])->assertOk();
    }

    public function test_new_refresh_token_can_be_refreshed_again(): void
    {
        $first = $this->signIn($this->createUser());
        $second = $this->refresh($first['refresh_token'])->assertOk()->json('data');

        $third = $this->refresh($second['refresh_token'])->assertOk()->json('data');

        $this->getMe($third['access_token'])->assertOk();
    }

    public function test_reusing_a_refresh_token_signs_that_device_out(): void
    {
        $old = $this->signIn($this->createUser());
        $new = $this->refresh($old['refresh_token'])->assertOk()->json('data');

        // The old refresh token comes back: someone may have copied it.
        $this->refresh($old['refresh_token'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');

        // Everything issued to that device is now revoked, including the newest pair.
        $this->getMe($new['access_token'])->assertUnauthorized();
        $this->refresh($new['refresh_token'])->assertUnauthorized();
    }

    public function test_reuse_on_one_device_does_not_affect_another_device(): void
    {
        $user = $this->createUser();
        $phone = $this->signIn($user, 'Phone');
        $tablet = $this->signIn($user, 'Tablet');

        $this->refresh($phone['refresh_token'])->assertOk();
        $this->refresh($phone['refresh_token'])->assertUnauthorized();

        $this->getMe($tablet['access_token'])->assertOk();
        $this->refresh($tablet['refresh_token'])->assertOk();
    }

    public function test_expired_refresh_token_is_rejected(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->travel(31)->days();

        $this->refresh($tokens['refresh_token'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_made_up_refresh_token_is_rejected(): void
    {
        $this->refresh(str_repeat('x', 64))->assertUnauthorized();
    }

    public function test_access_token_cannot_be_used_as_a_refresh_token(): void
    {
        $tokens = $this->signIn($this->createUser());

        $this->refresh($tokens['access_token'])->assertUnauthorized();
    }

    public function test_refresh_token_is_required(): void
    {
        $this->postJson('/api/v1/auth/refresh', [])->assertJsonValidationErrors('refresh_token');
    }
}
