<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Notifications\WallPostCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_post_and_trends_are_recorded(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->postJson('/api/posts', ['body' => 'Deicing in Minneapolis again tonight'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Deicing in Minneapolis again tonight')
            ->assertJsonPath('data.author.id', $me->id)
            ->assertJsonPath('data.can_delete', true);

        $this->assertDatabaseHas('trends', ['term' => 'deicing', 'hits' => 1]);
        $this->assertDatabaseMissing('trends', ['term' => 'again']); // stop word
    }

    public function test_html_is_stored_as_plain_text_not_rendered(): void
    {
        $me = User::factory()->create();

        // The API stores exactly what was typed; Angular escapes it when displaying.
        $this->actingAs($me)->postJson('/api/posts', ['body' => '<script>alert(1)</script>'])
            ->assertCreated()
            ->assertJsonPath('data.body', '<script>alert(1)</script>');
    }

    public function test_empty_post_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/posts', ['body' => ''])
            ->assertUnprocessable();
    }

    public function test_feed_shows_my_and_my_friends_posts_only(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $closedFriend = User::factory()->closed()->create();
        $this->makeFriends($me, $friend);
        $this->makeFriends($me, $closedFriend);

        $mine = Post::factory()->for($me, 'author')->create();
        $theirs = Post::factory()->for($friend, 'author')->create();
        Post::factory()->for($stranger, 'author')->create();
        Post::factory()->for($closedFriend, 'author')->create();

        $ids = collect($this->actingAs($me)->getJson('/api/feed')->assertOk()->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], $ids->all());
    }

    public function test_can_post_on_a_friends_profile_and_they_are_notified(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->makeFriends($me, $friend);

        $this->actingAs($me)->postJson('/api/posts', ['body' => 'Hi!', 'wall' => $friend->username])
            ->assertCreated()
            ->assertJsonPath('data.wall_user.id', $friend->id);

        Notification::assertSentTo($friend, WallPostCreated::class);
    }

    public function test_cannot_post_on_a_strangers_profile(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($me)->postJson('/api/posts', ['body' => 'Hi!', 'wall' => $stranger->username])
            ->assertForbidden();
    }

    public function test_only_author_or_wall_owner_can_delete(): void
    {
        $author = User::factory()->create();
        $wallOwner = User::factory()->create();
        $other = User::factory()->create();

        $post = Post::factory()->for($author, 'author')->create(['wall_user_id' => $wallOwner->id]);

        $this->actingAs($other)->deleteJson("/api/posts/{$post->id}")->assertForbidden();
        $this->assertNotSoftDeleted($post);

        $this->actingAs($wallOwner)->deleteJson("/api/posts/{$post->id}")->assertNoContent();
        $this->assertSoftDeleted($post);
    }

    public function test_image_upload_is_re_encoded_with_a_random_name(): void
    {
        Storage::fake('public');
        $me = User::factory()->create();

        $response = $this->actingAs($me)->post('/api/posts', [
            'body' => 'Sunset over the wing',
            'image' => UploadedFile::fake()->image('../../evil name.png', 2400, 1200),
        ])->assertCreated();

        $path = Post::first()->image_path;
        $this->assertMatchesRegularExpression('#^posts/[0-9a-f-]{36}\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1600, 800], [$w, $h], 'Large images are scaled down');
        $this->assertNotNull($response->json('data.image_url'));
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())->post('/api/posts', [
            'image' => UploadedFile::fake()->createWithContent('shell.php.png', '<?php system($_GET["c"]); ?>'),
        ])->assertUnprocessable();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_profile_shows_own_and_wall_posts(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $this->makeFriends($me, $friend);

        $own = Post::factory()->for($friend, 'author')->create();
        $onWall = Post::factory()->for($me, 'author')->create(['wall_user_id' => $friend->id]);
        $elsewhere = Post::factory()->for($friend, 'author')->create(['wall_user_id' => $me->id]);

        $ids = collect($this->actingAs($me)->getJson("/api/users/{$friend->username}/posts")->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$own->id, $onWall->id], $ids->all());
        $this->assertNotContains($elsewhere->id, $ids);
    }

    public function test_closed_profiles_are_hidden(): void
    {
        $closed = User::factory()->closed()->create();

        $this->actingAs(User::factory()->create())->getJson("/api/users/{$closed->username}")->assertNotFound();
    }
}
