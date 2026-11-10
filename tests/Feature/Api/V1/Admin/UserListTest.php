<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create(['first_name' => 'Admin']));
    }

    public function test_search_matches_name_or_email(): void
    {
        User::factory()->create(['first_name' => 'Nimal', 'last_name' => 'Perera', 'email' => 'np@example.com']);
        User::factory()->create(['first_name' => 'Kamal', 'last_name' => 'Silva', 'email' => 'kamal.s@example.com']);

        $this->getJson('/api/v1/admin/users?search=perera')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Nimal');

        $this->getJson('/api/v1/admin/users?search=kamal.s@')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Kamal');
    }

    public function test_percent_sign_in_search_is_not_a_wildcard(): void
    {
        User::factory()->create(['first_name' => 'Nimal']);

        $this->getJson('/api/v1/admin/users?search=%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filter_by_type(): void
    {
        User::factory()->create();
        User::factory()->inspector()->create(['first_name' => 'Kamal']);

        $this->getJson('/api/v1/admin/users?type='.UserType::Inspector->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Kamal');
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/users?type=9')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_results_are_paginated(): void
    {
        User::factory()->count(25)->create();

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.last_page', 2);
    }
}
