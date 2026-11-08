<?php

namespace Tests\Feature\Api\V1;

use App\Enums\District;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/api/v1/addresses'],
            'add' => ['post', '/api/v1/addresses'],
            'view' => ['get', '/api/v1/addresses/1'],
            'update' => ['patch', '/api/v1/addresses/1'],
            'delete' => ['delete', '/api/v1/addresses/1'],
        ];
    }

    #[DataProvider('routes')]
    public function test_guests_are_rejected(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    public function test_first_address_becomes_the_default(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/v1/addresses', $this->validData([
            'latitude' => 6.9271,
            'longitude' => 79.8612,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.district', 'colombo')
            ->assertJsonPath('data.latitude', 6.9271)
            ->assertJsonPath('data.longitude', 79.8612)
            ->assertJsonPath('data.is_default', true);

        $address = $user->addresses()->sole();
        $this->assertSame(District::Colombo, $address->district);
    }

    public function test_second_address_is_not_default_unless_asked(): void
    {
        $user = $this->actingAsUser();
        $first = UserAddress::factory()->for($user)->default()->create();

        $this->postJson('/api/v1/addresses', $this->validData(['label' => 'Office']))
            ->assertCreated()
            ->assertJsonPath('data.is_default', false);

        $this->postJson('/api/v1/addresses', $this->validData(['label' => 'Parents', 'is_default' => true]))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_update_can_switch_the_default(): void
    {
        $user = $this->actingAsUser();
        $home = UserAddress::factory()->for($user)->default()->create();
        $office = UserAddress::factory()->for($user)->create();

        $this->patchJson("/api/v1/addresses/{$office->id}", ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($home->fresh()->is_default);
        $this->assertTrue($office->fresh()->is_default);
    }

    public function test_unsetting_the_default_is_ignored(): void
    {
        $user = $this->actingAsUser();
        $home = UserAddress::factory()->for($user)->default()->create();

        $this->patchJson("/api/v1/addresses/{$home->id}", ['is_default' => false])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_deleting_the_default_promotes_the_newest_remaining(): void
    {
        $user = $this->actingAsUser();
        $home = UserAddress::factory()->for($user)->default()->create(['created_at' => now()->subDays(3)]);
        $older = UserAddress::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
        $newer = UserAddress::factory()->for($user)->create(['created_at' => now()->subDay()]);

        $this->deleteJson("/api/v1/addresses/{$home->id}")->assertNoContent();

        $this->assertDatabaseMissing('user_addresses', ['id' => $home->id]);
        $this->assertTrue($newer->fresh()->is_default);
        $this->assertFalse($older->fresh()->is_default);
    }

    public function test_list_shows_only_mine_with_default_first(): void
    {
        $user = $this->actingAsUser();
        $default = UserAddress::factory()->for($user)->default()->create(['created_at' => now()->subDay()]);
        $other = UserAddress::factory()->for($user)->create();
        UserAddress::factory()->create();

        $this->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $default->id)
            ->assertJsonPath('data.1.id', $other->id);
    }

    public function test_invalid_data_is_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/addresses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['line1', 'city', 'district']);

        $this->postJson('/api/v1/addresses', $this->validData([
            'district' => 'london',
            'postal_code' => '123',
            'latitude' => 6.9,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['district', 'postal_code', 'longitude']);
    }

    public function test_update_with_only_one_coordinate_is_rejected(): void
    {
        $user = $this->actingAsUser();
        $address = UserAddress::factory()->for($user)->default()->create();

        $this->patchJson("/api/v1/addresses/{$address->id}", ['latitude' => 6.9])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');
    }

    public function test_partial_update_changes_only_sent_fields(): void
    {
        $user = $this->actingAsUser();
        $address = UserAddress::factory()->for($user)->default()->create(['city' => 'Kandy', 'line1' => '1 Main St']);

        $this->patchJson("/api/v1/addresses/{$address->id}", ['line1' => '2 Temple Rd'])
            ->assertOk()
            ->assertJsonPath('data.line1', '2 Temple Rd')
            ->assertJsonPath('data.city', 'Kandy');
    }

    public function test_eleventh_address_is_refused(): void
    {
        $user = $this->actingAsUser();
        UserAddress::factory()->for($user)->count(10)->create();

        $this->postJson('/api/v1/addresses', $this->validData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address' => 'You can save up to 10 addresses.']);
    }

    public function test_someone_elses_address_returns_404(): void
    {
        $address = UserAddress::factory()->default()->create();
        $this->actingAsUser();

        $this->getJson("/api/v1/addresses/{$address->id}")->assertNotFound();
        $this->patchJson("/api/v1/addresses/{$address->id}", ['district' => 'nowhere'])->assertNotFound();
        $this->deleteJson("/api/v1/addresses/{$address->id}")->assertNotFound();

        $this->assertDatabaseHas('user_addresses', ['id' => $address->id]);
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
            'label' => 'Home',
            'line1' => '12 Galle Road',
            'line2' => 'Apartment 3B',
            'city' => 'Colombo 03',
            'district' => 'colombo',
            'postal_code' => '00300',
        ], $overrides);
    }
}
