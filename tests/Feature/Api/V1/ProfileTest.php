<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_includes_phone(): void
    {
        Sanctum::actingAs(User::factory()->create(['phone' => '+94771234567']));

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.phone', '+94771234567');
    }

    public function test_user_updates_names_and_phone(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['first_name' => 'Old', 'last_name' => 'Name']));

        $this->patchJson('/api/v1/me', [
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'phone' => '077 123 4567',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Nimal')
            ->assertJsonPath('data.last_name', 'Perera')
            ->assertJsonPath('data.phone', '+94771234567');

        $this->assertSame('Perera', $user->fresh()->last_name);
    }

    public function test_partial_update_keeps_other_fields(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['first_name' => 'Nimal', 'last_name' => 'Perera']));

        $this->patchJson('/api/v1/me', ['last_name' => 'Silva'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Nimal')
            ->assertJsonPath('data.last_name', 'Silva');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function phoneFormats(): array
    {
        return [
            'local' => ['0771234567'],
            'no leading zero' => ['771234567'],
            'international' => ['+94771234567'],
            'international without plus' => ['94771234567'],
            'with dashes' => ['077-123-4567'],
            'with spaces' => ['+94 77 123 4567'],
        ];
    }

    #[DataProvider('phoneFormats')]
    public function test_phone_formats_are_normalised(string $phone): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me', ['phone' => $phone])
            ->assertOk()
            ->assertJsonPath('data.phone', '+94771234567');
    }

    public function test_invalid_phone_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['12345', '07712345678', 'abcdefghij', '+1 555 123 4567'] as $phone) {
            $this->patchJson('/api/v1/me', ['phone' => $phone])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }
    }

    public function test_phone_can_be_cleared(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['phone' => '+94771234567']));

        $this->patchJson('/api/v1/me', ['phone' => null])
            ->assertOk()
            ->assertJsonPath('data.phone', null);

        $this->assertNull($user->fresh()->phone);
    }

    public function test_names_cannot_be_emptied(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me', ['first_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    public function test_email_and_type_cannot_be_changed(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['email' => 'nimal@example.com']));

        $this->patchJson('/api/v1/me', [
            'email' => 'other@example.com',
            'type' => UserType::Admin->value,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('nimal@example.com', $user->email);
        $this->assertSame(UserType::User, $user->type);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/v1/me', ['first_name' => 'X'])->assertUnauthorized();
    }
}
