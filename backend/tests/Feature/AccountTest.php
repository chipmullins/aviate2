<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_name_and_airline_email(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->putJson('/api/me/profile', ['first_name' => 'Sam', 'last_name' => 'Rivera', 'email' => 'sam@southwest.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'sam@southwest.com');
    }

    public function test_cannot_switch_to_a_non_airline_email(): void
    {
        $me = User::factory()->create(['email' => 'sam@delta.com']);

        $this->actingAs($me)->putJson('/api/me/profile', ['first_name' => 'Sam', 'last_name' => 'Rivera', 'email' => 'sam@gmail.com'])
            ->assertJsonValidationErrors('email');
        $this->assertSame('sam@delta.com', $me->fresh()->email);
    }

    public function test_cannot_take_another_users_email(): void
    {
        User::factory()->create(['email' => 'taken@delta.com']);
        $me = User::factory()->create();

        $this->actingAs($me)->putJson('/api/me/profile', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'taken@delta.com'])
            ->assertJsonValidationErrors('email');
    }

    public function test_password_change_requires_current_password(): void
    {
        $me = User::factory()->create(['password' => 'oldpass123']);

        $this->actingAs($me)->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($me)->putJson('/api/me/password', ['current_password' => 'oldpass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertNoContent();
        $this->assertTrue(Hash::check('newpass123', $me->fresh()->password));
    }

    public function test_avatar_upload_is_square_and_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $me = User::factory()->create();

        $this->actingAs($me)->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 900, 600)])->assertOk();
        $first = $me->fresh()->avatar_path;
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($first));
        $this->assertSame([400, 400], [$w, $h]);

        $this->actingAs($me)->post('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('b.png', 500, 500)])->assertOk();
        Storage::disk('public')->assertMissing($first);
    }

    public function test_closing_account_requires_password_and_hides_activity(): void
    {
        $me = User::factory()->create(['password' => 'secret123']);
        $viewer = User::factory()->create();
        $this->makeFriends($me, $viewer);
        Post::factory()->for($me, 'author')->create();

        $this->actingAs($me)->postJson('/api/me/close', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->actingAs($me)->postJson('/api/me/close', ['password' => 'secret123'])->assertNoContent();

        $this->assertNotNull($me->fresh()->closed_at);
        $this->actingAs($viewer)->getJson('/api/feed')->assertJsonCount(0, 'data');
        $this->actingAs($viewer)->getJson('/api/search/users?q='.$me->first_name)->assertJsonCount(0, 'data');
    }
}
