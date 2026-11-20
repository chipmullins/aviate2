<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostCommented;
use App\Notifications\PostLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentAndLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_liking_is_idempotent_and_notifies_once(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $this->actingAs($fan)->postJson("/api/posts/{$post->id}/like")->assertJson(['liked' => true, 'likes_count' => 1]);
        $this->actingAs($fan)->postJson("/api/posts/{$post->id}/like")->assertJson(['liked' => true, 'likes_count' => 1]);
        Notification::assertSentToTimes($author, PostLiked::class, 1);

        $this->actingAs($fan)->deleteJson("/api/posts/{$post->id}/like")->assertJson(['liked' => false, 'likes_count' => 0]);
    }

    public function test_liking_your_own_post_does_not_notify_you(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $post = Post::factory()->for($me, 'author')->create();

        $this->actingAs($me)->postJson("/api/posts/{$post->id}/like")->assertOk();
        Notification::assertNothingSent();
    }

    public function test_commenting_notifies_the_author(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $this->actingAs($other)->postJson("/api/posts/{$post->id}/comments", ['body' => 'Nice!'])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $other->id);

        Notification::assertSentTo($author, PostCommented::class);

        $this->actingAs($other)->getJson("/api/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_comment_can_be_deleted_by_commenter_or_post_author_only(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();
        $c1 = Comment::create(['post_id' => $post->id, 'user_id' => $commenter->id, 'body' => 'one']);
        $c2 = Comment::create(['post_id' => $post->id, 'user_id' => $commenter->id, 'body' => 'two']);

        $this->actingAs($other)->deleteJson("/api/comments/{$c1->id}")->assertForbidden();
        $this->actingAs($commenter)->deleteJson("/api/comments/{$c1->id}")->assertNoContent();
        $this->actingAs($author)->deleteJson("/api/comments/{$c2->id}")->assertNoContent();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_cannot_interact_with_deleted_posts(): void
    {
        $post = Post::factory()->create();
        $post->delete();
        $me = User::factory()->create();

        $this->actingAs($me)->postJson("/api/posts/{$post->id}/like")->assertNotFound();
        $this->actingAs($me)->postJson("/api/posts/{$post->id}/comments", ['body' => 'hi'])->assertNotFound();
    }
}
