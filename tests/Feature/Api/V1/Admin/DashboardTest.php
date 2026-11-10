<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_normal_users_and_inspectors_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();

        Sanctum::actingAs(User::factory()->inspector()->create());
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_empty_database_returns_zeros(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.users.total', 0)
            ->assertJsonPath('data.inspectors.total', 0)
            ->assertJsonPath('data.vehicles.total', 0);

        $byType = $response->json('data.vehicles.by_type');
        $this->assertSame(array_column(VehicleType::cases(), 'value'), array_keys($byType));
        $this->assertSame(0, array_sum($byType));
    }

    public function test_counts_are_correct(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->admin()->create();

        $users = User::factory()->count(3)->create();
        User::factory()->create()->delete();

        User::factory()->inspector()->count(2)->create();
        User::factory()->inspector()->create()->delete();

        Vehicle::factory()->for($users[0])->count(2)->create(['vehicle_type' => VehicleType::Car]);
        Vehicle::factory()->for($users[1])->create(['vehicle_type' => VehicleType::Motorcycle]);
        Vehicle::factory()->for($users[2])->create(['vehicle_type' => VehicleType::Van])->delete();

        $response = $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.users.total', 3)
            ->assertJsonPath('data.inspectors.total', 2)
            ->assertJsonPath('data.vehicles.total', 3)
            ->assertJsonPath('data.vehicles.by_type.car', 2)
            ->assertJsonPath('data.vehicles.by_type.motorcycle', 1)
            ->assertJsonPath('data.vehicles.by_type.van', 0)
            ->assertJsonPath('data.vehicles.by_type.bus', 0);

        $this->assertSame(3, array_sum($response->json('data.vehicles.by_type')));
    }
}
