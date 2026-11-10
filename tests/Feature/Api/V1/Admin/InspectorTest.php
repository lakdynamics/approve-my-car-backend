<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\V1\Auth\SignsInUsers;
use Tests\TestCase;

class InspectorTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /**
     * @return array<string, array{string, string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'list users' => ['get', '/api/v1/admin/users'],
            'list inspectors' => ['get', '/api/v1/admin/inspectors'],
            'create inspector' => ['post', '/api/v1/admin/inspectors'],
            'promote' => ['put', '/api/v1/admin/inspectors/1'],
            'demote' => ['delete', '/api/v1/admin/inspectors/1'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_guests_are_rejected(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('adminRoutes')]
    public function test_normal_users_are_forbidden(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->create(['id' => 1]));

        $this->json($method, $uri)->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_inspectors_are_forbidden(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->inspector()->create(['id' => 1]));

        $this->json($method, $uri)->assertForbidden();
    }

    public function test_admin_creates_an_inspector_who_can_sign_in(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/inspectors', [
            'first_name' => 'Kamal',
            'last_name' => 'Silva',
            'email' => 'Kamal@Example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'kamal@example.com')
            ->assertJsonPath('data.type', UserType::Inspector->value)
            ->assertJsonMissingPath('data.password');

        $inspector = User::where('email', 'kamal@example.com')->sole();
        $this->assertSame(UserType::Inspector, $inspector->type);
        $this->assertNotNull($inspector->email_verified_at);

        $this->app['auth']->forgetGuards();
        $this->assertSame(UserType::Inspector->value, $this->signIn($inspector)['user']['type']);
    }

    public function test_creating_an_inspector_with_a_taken_email_fails(): void
    {
        $this->actingAsAdmin();
        $this->createUser('taken@example.com');

        $this->postJson('/api/v1/admin/inspectors', [
            'first_name' => 'Kamal',
            'last_name' => 'Silva',
            'email' => 'taken@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_promoting_a_user_makes_them_an_inspector_and_signs_them_out(): void
    {
        $user = $this->createUser();
        $tokens = $this->signIn($user);

        $this->actingAsAdmin();

        $this->putJson("/api/v1/admin/inspectors/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.type', UserType::Inspector->value);

        $this->assertSame(UserType::Inspector, $user->fresh()->type);
        $this->getMe($tokens['access_token'])->assertUnauthorized();
        $this->refresh($tokens['refresh_token'])->assertUnauthorized();
    }

    public function test_promoting_an_inspector_again_changes_nothing(): void
    {
        $inspector = User::factory()->inspector()->create(['password' => self::PASSWORD]);
        $tokens = $this->signIn($inspector);

        $this->actingAsAdmin();

        $this->putJson("/api/v1/admin/inspectors/{$inspector->id}")
            ->assertOk()
            ->assertJsonPath('data.type', UserType::Inspector->value);

        $this->getMe($tokens['access_token'])->assertOk();
    }

    public function test_an_admin_cannot_be_made_an_inspector(): void
    {
        $other = User::factory()->admin()->create();
        $this->actingAsAdmin();

        $this->putJson("/api/v1/admin/inspectors/{$other->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_USER_TYPE');

        $this->assertSame(UserType::Admin, $other->fresh()->type);
    }

    public function test_demoting_an_inspector_makes_them_a_user_and_signs_them_out(): void
    {
        $inspector = User::factory()->inspector()->create(['password' => self::PASSWORD]);
        $tokens = $this->signIn($inspector);

        $this->actingAsAdmin();

        $this->deleteJson("/api/v1/admin/inspectors/{$inspector->id}")
            ->assertOk()
            ->assertJsonPath('data.type', UserType::User->value);

        $this->assertSame(UserType::User, $inspector->fresh()->type);
        $this->getMe($tokens['access_token'])->assertUnauthorized();
        $this->refresh($tokens['refresh_token'])->assertUnauthorized();
    }

    public function test_demoting_someone_who_is_not_an_inspector_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAsAdmin();

        $this->deleteJson("/api/v1/admin/inspectors/{$user->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_USER_TYPE');
    }

    public function test_unknown_user_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/inspectors/999999')->assertNotFound();
    }

    public function test_listing_inspectors_returns_only_inspectors(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['first_name' => 'Nimal']);
        User::factory()->inspector()->create(['first_name' => 'Kamal']);
        User::factory()->inspector()->create(['first_name' => 'Sunil']);

        $this->getJson('/api/v1/admin/inspectors')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.first_name', 'Kamal')
            ->assertJsonPath('data.1.first_name', 'Sunil');

        $this->getJson('/api/v1/admin/inspectors?search=sun')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Sunil');
    }

    private function actingAsAdmin(): User
    {
        return Sanctum::actingAs(User::factory()->admin()->create());
    }
}
