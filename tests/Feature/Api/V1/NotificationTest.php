<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\BookingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function routes(): array
    {
        return [
            'history' => ['get', '/api/v1/notifications'],
            'count' => ['get', '/api/v1/notifications/unread-count'],
            'read one' => ['patch', '/api/v1/notifications/'.Str::uuid().'/read'],
            'read all' => ['patch', '/api/v1/notifications/read-all'],
        ];
    }

    #[DataProvider('routes')]
    public function test_guests_cannot_access_notifications(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    public function test_history_is_paginated_filtered_and_private(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $other = User::factory()->create();
        $this->notify($other);
        $this->notify($user);
        $read = $user->notifications()->first();
        $read->markAsRead();
        $this->travel(1)->seconds();
        $this->notify($user);
        $newest = $user->notifications()->first();

        $this->getJson('/api/v1/notifications?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/notifications?filter=read')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $read->id);
        $this->getJson('/api/v1/notifications?filter=unread')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.data.booking_id', 123);
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unread_count', 1);
    }

    public function test_marking_read_is_idempotent_and_history_is_retained(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $this->notify($user);
        $id = $user->notifications()->first()->id;
        $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk();
        $readAt = $user->notifications()->first()->read_at->toISOString();
        $this->travel(1)->minutes();
        $this->patchJson("/api/v1/notifications/{$id}/read")
            ->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_read_operations_do_not_affect_other_users(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $other = User::factory()->create();
        $this->notify($user);
        $this->notify($other);
        $otherId = $other->notifications()->first()->id;

        $this->patchJson("/api/v1/notifications/{$otherId}/read")->assertNotFound();
        $this->patchJson('/api/v1/notifications/'.Str::uuid().'/read')->assertNotFound();
        $this->patchJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 1);
        $this->patchJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 0);
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_invalid_filters_and_pagination_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/notifications?filter=bad&per_page=101&page=0')
            ->assertUnprocessable()->assertJsonValidationErrors(['filter', 'per_page', 'page']);
    }

    private function notify(User $user): void
    {
        $user->notify(new BookingNotification([
            'event' => 'booking.accepted',
            'title' => 'Booking accepted',
            'body' => 'An inspector has accepted your booking.',
            'booking_id' => 123,
            'status' => 'accepted',
        ]));
    }
}
