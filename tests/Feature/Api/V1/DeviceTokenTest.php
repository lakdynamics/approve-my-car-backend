<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Auth\SignsInUsers;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_guests_cannot_register_or_remove_devices(): void
    {
        $this->postJson('/api/v1/device-tokens')->assertUnauthorized();
        $this->deleteJson('/api/v1/device-tokens/'.Str::uuid())->assertUnauthorized();
    }

    public function test_registration_is_idempotent_and_token_rotation_replaces_old_token(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $data = ['device_id' => (string) Str::uuid(), 'platform' => 'android', 'token' => 'fcm-original'];
        $this->postJson('/api/v1/device-tokens', $data)->assertCreated()
            ->assertJsonPath('data.device_id', $data['device_id'])->assertJsonMissingPath('data.token');
        $this->postJson('/api/v1/device-tokens', $data)->assertOk();
        $this->postJson('/api/v1/device-tokens', [...$data, 'token' => 'fcm-refreshed'])->assertOk();

        $device = DeviceToken::sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('fcm-refreshed', $device->token);
        $this->assertNotSame('fcm-refreshed', $device->getRawOriginal('token'));
        $this->assertSame(hash('sha256', 'fcm-refreshed'), $device->token_hash);
    }

    public function test_shared_device_and_duplicate_token_are_transferred_to_current_user(): void
    {
        $first = User::factory()->create();
        $second = Sanctum::actingAs(User::factory()->create());
        $old = DeviceToken::factory()->for($first)->create();
        $secondDevice = DeviceToken::factory()->for($second)->create();

        $this->postJson('/api/v1/device-tokens', [
            'device_id' => $secondDevice->device_id, 'platform' => 'ios', 'token' => $old->token,
        ])->assertOk();
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame($second->id, DeviceToken::sole()->user_id);

        Sanctum::actingAs($first);
        $this->postJson('/api/v1/device-tokens', [
            'device_id' => $secondDevice->device_id, 'platform' => 'ios', 'token' => 'new-token',
        ])->assertOk();
        $this->assertSame($first->id, DeviceToken::sole()->user_id);
    }

    public function test_users_can_have_multiple_devices_and_only_remove_their_own(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $own = DeviceToken::factory()->for($user)->create();
        DeviceToken::factory()->for($user)->create();
        $other = DeviceToken::factory()->create();

        $this->deleteJson('/api/v1/device-tokens/'.$other->device_id)->assertNotFound();
        $this->deleteJson('/api/v1/device-tokens/'.$own->device_id)->assertNoContent();
        $this->assertDatabaseCount('device_tokens', 2);
    }

    public function test_invalid_device_data_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/device-tokens', [
            'device_id' => 'not-a-uuid', 'platform' => 'unknown', 'token' => 'bad token',
        ])->assertUnprocessable()->assertJsonValidationErrors(['device_id', 'platform', 'token']);
        $this->postJson('/api/v1/device-tokens', [
            'device_id' => (string) Str::uuid(), 'platform' => 'android', 'token' => str_repeat('a', 4097),
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    }

    public function test_session_refresh_keeps_device_and_logout_removes_it(): void
    {
        $user = $this->createUser();
        $tokens = $this->signIn($user);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/device-tokens', [
            'device_id' => (string) Str::uuid(), 'platform' => 'android', 'token' => 'fcm-session',
        ], ['Authorization' => 'Bearer '.$tokens['access_token']])->assertCreated();
        $this->assertNotNull(DeviceToken::sole()->family_id);

        $newTokens = $this->refresh($tokens['refresh_token'])->assertOk()->json('data');
        $this->assertDatabaseCount('device_tokens', 1);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$newTokens['access_token']])
            ->assertNoContent();
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_revoking_other_sessions_preserves_only_current_devices(): void
    {
        $user = User::factory()->create();
        $other = DeviceToken::factory()->create();
        app(TokenService::class)->issue($user, 'first');
        app(TokenService::class)->issue($user, 'second');
        $sessions = $user->refreshTokens()->orderBy('id')->get();
        DeviceToken::factory()->for($user)->create(['family_id' => $sessions[0]->family_id]);
        $keep = DeviceToken::factory()->for($user)->create(['family_id' => $sessions[1]->family_id]);
        DeviceToken::factory()->for($user)->create();

        app(TokenService::class)->revokeAllExcept($user, PersonalAccessToken::findOrFail($sessions[1]->access_token_id));
        $this->assertSame([$keep->id], $user->deviceTokens()->pluck('id')->all());
        $this->assertDatabaseHas('device_tokens', ['id' => $other->id]);

        app(TokenService::class)->revokeAllFor($user);
        $this->assertSame(0, $user->deviceTokens()->count());
    }
}
