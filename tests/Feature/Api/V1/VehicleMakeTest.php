<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use Database\Seeders\VehicleMakeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleMakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/v1/vehicle-makes')->assertUnauthorized();
    }

    public function test_dropdown_lists_active_makes_a_to_z(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $toyota = VehicleMake::factory()->create(['name' => 'Toyota']);
        $bajaj = VehicleMake::factory()->create(['name' => 'Bajaj']);
        VehicleMake::factory()->hidden()->create(['name' => 'Austin']);

        $this->getJson('/api/v1/vehicle-makes')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $bajaj->id, 'name' => 'Bajaj'],
                ['id' => $toyota->id, 'name' => 'Toyota'],
            ]]);
    }

    public function test_seeder_adds_makes_and_links_existing_vehicles(): void
    {
        $vehicle = Vehicle::factory()->create(['make' => 'toyota']);
        $other = Vehicle::factory()->create(['make' => 'Zastava']);

        $this->seed(VehicleMakeSeeder::class);
        $this->seed(VehicleMakeSeeder::class); // safe to run twice

        $this->assertSame(count(VehicleMakeSeeder::MAKES), VehicleMake::count());

        $vehicle->refresh();
        $this->assertSame('Toyota', $vehicle->make);
        $this->assertSame(VehicleMake::where('name', 'Toyota')->value('id'), $vehicle->make_id);
        $this->assertNull($other->fresh()->make_id);
    }

    public function test_non_admins_cannot_manage_makes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/vehicle-makes')->assertForbidden();
        $this->postJson('/api/v1/admin/vehicle-makes', ['name' => 'Tesla'])->assertForbidden();

        Sanctum::actingAs(User::factory()->inspector()->create());
        $this->postJson('/api/v1/admin/vehicle-makes', ['name' => 'Tesla'])->assertForbidden();
    }

    public function test_admin_lists_makes_with_counts(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $toyota = VehicleMake::factory()->create(['name' => 'Toyota']);
        VehicleMake::factory()->hidden()->create(['name' => 'Austin']);
        Vehicle::factory()->count(2)->create(['make_id' => $toyota->id, 'make' => 'Toyota']);

        $this->getJson('/api/v1/admin/vehicle-makes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicles_count', 2)
            ->assertJsonPath('data.0.is_active', true);

        $this->getJson('/api/v1/admin/vehicle-makes?include_inactive=1')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Austin');

        $this->getJson('/api/v1/admin/vehicle-makes?search=toy&include_inactive=1')
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_adds_a_make(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        VehicleMake::factory()->create(['name' => 'Toyota']);

        $this->postJson('/api/v1/admin/vehicle-makes', ['name' => '  Tesla '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tesla')
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/v1/admin/vehicle-makes', ['name' => 'toyota'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'This make already exists.']);
    }

    public function test_renaming_updates_linked_vehicles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $make = VehicleMake::factory()->create(['name' => 'Mercedes']);
        $vehicle = Vehicle::factory()->create(['make_id' => $make->id, 'make' => 'Mercedes']);

        $this->patchJson("/api/v1/admin/vehicle-makes/{$make->id}", ['name' => 'Mercedes-Benz'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mercedes-Benz');

        $this->assertSame('Mercedes-Benz', $vehicle->fresh()->make);
    }

    public function test_hiding_removes_it_from_the_dropdown_only(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $make = VehicleMake::factory()->create(['name' => 'Austin']);
        $vehicle = Vehicle::factory()->create(['make_id' => $make->id, 'make' => 'Austin']);

        $this->patchJson("/api/v1/admin/vehicle-makes/{$make->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/vehicle-makes')->assertJsonCount(0, 'data');
        $this->assertSame($make->id, $vehicle->fresh()->make_id);
    }
}
