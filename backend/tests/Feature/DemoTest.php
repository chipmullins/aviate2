<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    private function demoUser(array $attrs = []): User
    {
        $user = User::factory()->create(['username' => 'maya.chen', ...$attrs]);
        $user->forceFill(['is_demo' => true])->save();

        return $user;
    }

    public function test_demo_is_off_by_default(): void
    {
        $this->demoUser();

        $this->getJson('/api/demo')->assertExactJson(['enabled' => false, 'accounts' => []]);
        $this->postJson('/api/demo/login', ['username' => 'maya.chen'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_one_click_demo_login_when_enabled(): void
    {
        config(['aviate.demo.enabled' => true]);
        $maya = $this->demoUser();

        $this->getJson('/api/demo')
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('accounts.0.username', 'maya.chen')
            ->assertJsonMissingPath('accounts.0.email');

        $this->postJson('/api/demo/login', ['username' => 'maya.chen'])
            ->assertOk()
            ->assertJsonPath('data.is_demo', true);
        $this->assertAuthenticatedAs($maya);
    }

    public function test_demo_login_never_works_for_real_accounts(): void
    {
        config(['aviate.demo.enabled' => true]);
        User::factory()->create(['username' => 'real.person']);

        $this->postJson('/api/demo/login', ['username' => 'real.person'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_accounts_cannot_be_changed_or_closed_in_demo_mode(): void
    {
        config(['aviate.demo.enabled' => true]);
        $maya = $this->demoUser(['email' => 'maya.chen@delta.com', 'password' => 'aviate-demo-1']);

        $this->actingAs($maya)->putJson('/api/me/password', ['current_password' => 'aviate-demo-1', 'password' => 'hijacked123', 'password_confirmation' => 'hijacked123'])->assertForbidden();
        $this->actingAs($maya)->putJson('/api/me/profile', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'other@delta.com'])->assertForbidden();
        $this->actingAs($maya)->postJson('/api/me/close', ['password' => 'aviate-demo-1'])->assertForbidden();
        $this->actingAs($maya)->deleteJson('/api/me/avatar')->assertForbidden();

        $this->assertSame('maya.chen@delta.com', $maya->fresh()->email);
        $this->assertNull($maya->fresh()->closed_at);
    }

    public function test_demo_accounts_work_normally_when_demo_mode_is_off(): void
    {
        $maya = $this->demoUser(['password' => 'aviate-demo-1']);

        $this->actingAs($maya)->putJson('/api/me/password', ['current_password' => 'aviate-demo-1', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertNoContent();
    }

    public function test_reset_command_refuses_outside_demo_mode(): void
    {
        User::factory()->create();

        $this->assertSame(1, Artisan::call('aviate:reset-demo', ['--force' => true]));
        $this->assertDatabaseCount('users', 1);
    }
}
