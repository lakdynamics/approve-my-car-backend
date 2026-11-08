<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\Vehicle;
use App\Notifications\Auth\RegistrationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Auth\SignsInUsers;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_wrong_password_keeps_the_account(): void
    {
        $user = $this->createUser();
        $tokens = $this->signIn($user);

        $this->deleteAccount($tokens['access_token'], 'WrongPassword1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Your password is incorrect.']);

        $this->assertNotSoftDeleted($user);
        $this->getMe($tokens['access_token'])->assertOk();
    }

    public function test_deleting_the_account(): void
    {
        $user = $this->createUser('nimal@example.com');
        $user->forceFill(['phone' => '+94771234567'])->save();
        $vehicle = Vehicle::factory()->for($user)->create();
        UserAddress::factory()->for($user)->default()->create();
        $tokens = $this->signIn($user);
        $otherDevice = $this->signIn($user, 'Tablet');

        $this->deleteAccount($tokens['access_token'], self::PASSWORD)->assertNoContent();

        // Signed out everywhere.
        $this->getMe($tokens['access_token'])->assertUnauthorized();
        $this->getMe($otherDevice['access_token'])->assertUnauthorized();
        $this->refresh($otherDevice['refresh_token'])->assertUnauthorized();

        // Soft deleted, with personal details wiped.
        $this->assertSoftDeleted($user);
        $deleted = User::withTrashed()->find($user->id);
        $this->assertSame('Deleted', $deleted->first_name);
        $this->assertSame('User', $deleted->last_name);
        $this->assertNull($deleted->phone);
        $this->assertSame("deleted-{$user->id}@deleted.invalid", $deleted->email);

        // Vehicles kept for history, addresses removed.
        $this->assertSoftDeleted($vehicle);
        $this->assertDatabaseCount('user_addresses', 0);

        // The old email can no longer sign in.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nimal@example.com',
            'password' => self::PASSWORD,
            'device_name' => 'Phone',
        ])->assertUnprocessable();
    }

    public function test_the_email_can_sign_up_again(): void
    {
        Notification::fake();
        $user = $this->createUser('nimal@example.com');
        $tokens = $this->signIn($user);
        $this->deleteAccount($tokens['access_token'], self::PASSWORD)->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'email' => 'nimal@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertStatus(202);

        // A real sign-up code is sent, not the "account already exists" email.
        Notification::assertSentOnDemand(RegistrationCodeNotification::class);
    }

    public function test_deleted_user_is_not_in_the_admin_list(): void
    {
        $user = $this->createUser();
        $tokens = $this->signIn($user);
        $this->deleteAccount($tokens['access_token'], self::PASSWORD)->assertNoContent();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_delete(): void
    {
        $this->deleteJson('/api/v1/me', ['password' => 'x'])->assertUnauthorized();
    }

    private function deleteAccount(string $accessToken, string $password): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->deleteJson('/api/v1/me', ['password' => $password], ['Authorization' => 'Bearer '.$accessToken]);
    }
}
