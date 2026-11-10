<?php

namespace Tests\Feature\Console;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_makes_an_existing_user_an_admin(): void
    {
        $user = User::factory()->create(['email' => 'nimal@example.com']);
        $user->createToken('Test phone');

        $this->artisan('user:make-admin', ['email' => 'Nimal@Example.com'])
            ->expectsOutput('nimal@example.com is now an admin.')
            ->assertSuccessful();

        $this->assertSame(UserType::Admin, $user->fresh()->type);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])
            ->assertFailed();
    }
}
