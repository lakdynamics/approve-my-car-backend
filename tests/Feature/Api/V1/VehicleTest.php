<?php

namespace Tests\Feature\Api\V1;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/api/v1/vehicles'],
            'add' => ['post', '/api/v1/vehicles'],
            'view' => ['get', '/api/v1/vehicles/1'],
            'update' => ['patch', '/api/v1/vehicles/1'],
            'delete' => ['delete', '/api/v1/vehicles/1'],
        ];
    }

    #[DataProvider('routes')]
    public function test_guests_are_rejected(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    public function test_user_adds_a_vehicle(): void
    {
        $user = $this->actingAsUser();
        $other = User::factory()->create();

        $this->postJson('/api/v1/vehicles', $this->validData([
            'registration_number' => '  wp   cab 1234 ',
            'chassis_number' => 'nze161-123',
            'user_id' => $other->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.registration_number', 'WP CAB 1234')
            ->assertJsonPath('data.make', 'Toyota')
            ->assertJsonPath('data.vehicle_type', 'car')
            ->assertJsonPath('data.fuel_type', 'hybrid')
            ->assertJsonPath('data.chassis_number', 'NZE161-123')
            ->assertJsonMissingPath('data.user_id');

        $vehicle = Vehicle::sole();
        $this->assertSame($user->id, $vehicle->user_id);
        $this->assertSame(VehicleType::Car, $vehicle->vehicle_type);
    }

    public function test_optional_fields_can_be_left_out(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/vehicles', [
            'registration_number' => 'CAB 1234',
            'make' => 'Toyota',
            'model' => 'Aqua',
            'year' => 2015,
            'vehicle_type' => 'car',
        ])
            ->assertCreated()
            ->assertJsonPath('data.fuel_type', null)
            ->assertJsonPath('data.mileage', null);
    }

    public function test_invalid_data_is_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/vehicles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_number', 'make', 'model', 'year', 'vehicle_type']);

        $this->postJson('/api/v1/vehicles', $this->validData([
            'vehicle_type' => 'spaceship',
            'fuel_type' => 'coal',
            'transmission' => 'cvt',
            'year' => 1900,
            'mileage' => -5,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_type', 'fuel_type', 'transmission', 'year', 'mileage']);

        $this->postJson('/api/v1/vehicles', $this->validData(['year' => (int) date('Y') + 2]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');
    }

    public function test_same_registration_number_twice_for_one_user_is_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/vehicles', $this->validData(['registration_number' => 'CAB 1234']))->assertCreated();

        $this->postJson('/api/v1/vehicles', $this->validData(['registration_number' => 'cab  1234']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_number' => 'You have already added a vehicle with this registration number.']);
    }

    public function test_another_user_can_add_the_same_registration_number(): void
    {
        Vehicle::factory()->create(['registration_number' => 'CAB 1234']);

        $this->actingAsUser();

        $this->postJson('/api/v1/vehicles', $this->validData(['registration_number' => 'CAB 1234']))->assertCreated();
    }

    public function test_a_deleted_vehicle_can_be_added_again(): void
    {
        $user = $this->actingAsUser();
        Vehicle::factory()->for($user)->create(['registration_number' => 'CAB 1234'])->delete();

        $this->postJson('/api/v1/vehicles', $this->validData(['registration_number' => 'CAB 1234']))->assertCreated();
    }

    public function test_list_shows_only_my_vehicles_newest_first(): void
    {
        $user = $this->actingAsUser();
        $older = Vehicle::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $newer = Vehicle::factory()->for($user)->create();
        Vehicle::factory()->create();

        $this->getJson('/api/v1/vehicles')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_list_is_paginated(): void
    {
        $user = $this->actingAsUser();
        Vehicle::factory()->for($user)->count(25)->create();

        $this->getJson('/api/v1/vehicles')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25);
    }

    public function test_user_views_their_vehicle(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);
    }

    public function test_someone_elses_vehicle_returns_404(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->actingAsUser();

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertNotFound();
        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", ['year' => 1800])->assertNotFound();
        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertNotFound();

        $this->assertNotSoftDeleted($vehicle);
    }

    public function test_partial_update_changes_only_sent_fields(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create([
            'registration_number' => 'CAB 1234',
            'make' => 'Toyota',
            'mileage' => 50000,
        ]);

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", [
            'registration_number' => 'CAB 1234',
            'mileage' => 65000,
        ])
            ->assertOk()
            ->assertJsonPath('data.mileage', 65000)
            ->assertJsonPath('data.make', 'Toyota');

        $this->assertSame(65000, $vehicle->fresh()->mileage);
    }

    public function test_update_cannot_take_another_of_my_registration_numbers(): void
    {
        $user = $this->actingAsUser();
        Vehicle::factory()->for($user)->create(['registration_number' => 'CAB 1111']);
        $vehicle = Vehicle::factory()->for($user)->create(['registration_number' => 'CAB 2222']);

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", ['registration_number' => 'cab 1111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registration_number');
    }

    public function test_update_rejects_clearing_a_required_field(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", ['make' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('make');
    }

    public function test_delete_hides_the_vehicle_but_keeps_the_row(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertNoContent();

        $this->assertSoftDeleted($vehicle);
        $this->getJson('/api/v1/vehicles')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertNotFound();
    }

    public function test_make_picked_from_the_list(): void
    {
        $this->actingAsUser();
        $toyota = VehicleMake::factory()->create(['name' => 'Toyota']);

        $this->postJson('/api/v1/vehicles', $this->validData(['make_id' => $toyota->id, 'make' => null]))
            ->assertCreated()
            ->assertJsonPath('data.make_id', $toyota->id)
            ->assertJsonPath('data.make', 'Toyota');
    }

    public function test_hidden_or_unknown_make_id_is_rejected(): void
    {
        $this->actingAsUser();
        $hidden = VehicleMake::factory()->hidden()->create();

        foreach ([$hidden->id, 999999] as $makeId) {
            $this->postJson('/api/v1/vehicles', $this->validData(['make_id' => $makeId]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['make_id' => 'Select a make from the list.']);
        }
    }

    public function test_typed_make_is_linked_when_it_matches_the_list(): void
    {
        $this->actingAsUser();
        $toyota = VehicleMake::factory()->create(['name' => 'Toyota']);

        $this->postJson('/api/v1/vehicles', $this->validData(['make' => 'toyota']))
            ->assertCreated()
            ->assertJsonPath('data.make_id', $toyota->id)
            ->assertJsonPath('data.make', 'Toyota');
    }

    public function test_other_make_keeps_the_typed_text(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/vehicles', $this->validData(['make_id' => null, 'make' => 'Zastava']))
            ->assertCreated()
            ->assertJsonPath('data.make_id', null)
            ->assertJsonPath('data.make', 'Zastava');
    }

    public function test_make_is_required_one_way_or_the_other(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/vehicles', $this->validData(['make' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['make' => 'Select a make, or choose Other and type it.']);
    }

    public function test_vehicle_with_a_hidden_make_can_still_be_edited(): void
    {
        $user = $this->actingAsUser();
        $make = VehicleMake::factory()->hidden()->create(['name' => 'Austin']);
        $vehicle = Vehicle::factory()->for($user)->create(['make_id' => $make->id, 'make' => 'Austin']);

        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", ['make_id' => $make->id, 'color' => 'Red'])
            ->assertOk()
            ->assertJsonPath('data.make', 'Austin')
            ->assertJsonPath('data.color', 'Red');
    }

    private function actingAsUser(): User
    {
        return Sanctum::actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'registration_number' => 'CAB 1234',
            'make' => 'Toyota',
            'model' => 'Aqua',
            'year' => 2015,
            'vehicle_type' => 'car',
            'fuel_type' => 'hybrid',
            'transmission' => 'automatic',
            'color' => 'White',
            'mileage' => 85000,
        ], $overrides);
    }
}
