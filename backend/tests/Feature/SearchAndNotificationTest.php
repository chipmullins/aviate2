<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_names_and_never_returns_emails(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['first_name' => 'Amelia', 'last_name' => 'Earhart', 'username' => 'amelia.earhart']);
        User::factory()->create(['first_name' => 'Chuck', 'last_name' => 'Yeager', 'username' => 'chuck.yeager']);

        $this->actingAs($me)->getJson('/api/search/users?q=ame%20ear')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'amelia.earhart')
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_like_wildcards_are_treated_as_plain_text(): void
    {
        $me = User::factory()->create();
        User::factory()->count(3)->create();

        $this->actingAs($me)->getJson('/api/search/users?q=%25')->assertJsonCount(0, 'data');
        $this->actingAs($me)->getJson('/api/search/users?q=_')->assertJsonCount(0, 'data');
    }

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $this->actingAs($fan)->postJson("/api/posts/{$post->id}/like");

        $this->actingAs($author)->getJson('/api/me/counts')->assertJsonPath('notifications', 1);
        $this->actingAs($author)->getJson('/api/notifications')
            ->assertJsonPath('data.0.kind', 'post_liked')
            ->assertJsonPath('data.0.actor.id', $fan->id)
            ->assertJsonPath('data.0.post_id', $post->id);

        $this->actingAs($author)->postJson('/api/notifications/read-all')->assertNoContent();
        $this->actingAs($author)->getJson('/api/me/counts')->assertJsonPath('notifications', 0);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();
        $this->actingAs($fan)->postJson("/api/posts/{$post->id}/like");
        $id = $author->notifications()->first()->id;

        $this->actingAs($fan)->postJson("/api/notifications/{$id}/read")->assertNotFound();
    }
}
