<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FriendTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_friend_request_flow(): void
    {
        Notification::fake();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson("/api/users/{$b->username}/friend-request")
            ->assertJson(['friendship_status' => 'request_sent']);
        Notification::assertSentTo($b, FriendRequestReceived::class);

        $this->actingAs($b)->getJson('/api/friend-requests')->assertJsonPath('data.0.sender.id', $a->id);
        $this->actingAs($b)->getJson('/api/me/counts')->assertJsonPath('friend_requests', 1);

        $this->actingAs($b)->postJson("/api/users/{$a->username}/friend-request/accept")
            ->assertJson(['friendship_status' => 'friends']);
        Notification::assertSentTo($a, FriendRequestAccepted::class);

        $this->assertTrue($a->isFriendsWith($b) && $b->isFriendsWith($a));
        $this->assertDatabaseCount('friend_requests', 0);

        $this->actingAs($a)->deleteJson("/api/users/{$b->username}/friend")->assertJson(['friendship_status' => 'none']);
        $this->assertFalse($b->isFriendsWith($a));
    }

    public function test_requesting_someone_who_already_requested_you_accepts_it(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson("/api/users/{$b->username}/friend-request");
        $this->actingAs($b)->postJson("/api/users/{$a->username}/friend-request")
            ->assertJson(['friendship_status' => 'friends']);
    }

    public function test_cannot_accept_a_request_that_was_never_sent(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson("/api/users/{$b->username}/friend-request/accept")->assertNotFound();
        $this->assertFalse($a->isFriendsWith($b));
    }

    public function test_cannot_friend_yourself_or_a_closed_account(): void
    {
        $me = User::factory()->create();
        $closed = User::factory()->closed()->create();

        $this->actingAs($me)->postJson("/api/users/{$me->username}/friend-request")->assertStatus(422);
        $this->actingAs($me)->postJson("/api/users/{$closed->username}/friend-request")->assertNotFound();
    }

    public function test_profile_shows_mutual_friends_and_status(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        $shared = User::factory()->create();
        $this->makeFriends($me, $shared);
        $this->makeFriends($them, $shared);

        $this->actingAs($me)->getJson("/api/users/{$them->username}")
            ->assertOk()
            ->assertJsonPath('data.mutual_friends_count', 1)
            ->assertJsonPath('data.friendship_status', 'none')
            ->assertJsonMissingPath('data.email');
    }
}
