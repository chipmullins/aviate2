<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Pilot',
            'email' => 'jane.pilot@delta.com',
            'password' => 'flyhigh123',
            'password_confirmation' => 'flyhigh123',
            ...$overrides,
        ];
    }

    public function test_airline_employee_can_register_and_is_signed_in(): void
    {
        $this->postJson('/api/register', $this->registration())
            ->assertCreated()
            ->assertJsonPath('data.username', 'jane.pilot')
            ->assertJsonPath('data.email', 'jane.pilot@delta.com')
            ->assertJsonPath('data.airline', 'Delta Air Lines')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticated();
        $this->assertNotSame('flyhigh123', User::first()->password, 'Password must be hashed');
    }

    public static function rejectedEmails(): array
    {
        return [
            'personal email' => ['jane@gmail.com'],
            'lookalike domain' => ['jane@notdelta.com'],
            'domain as subdomain of another' => ['jane@delta.com.evil.net'],
            'airline subdomain' => ['jane@ops.delta.com'],
        ];
    }

    #[DataProvider('rejectedEmails')]
    public function test_non_airline_emails_are_rejected(string $email): void
    {
        $this->postJson('/api/register', $this->registration(['email' => $email]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_email_domain_check_ignores_case(): void
    {
        $this->postJson('/api/register', $this->registration(['email' => 'Jane.Pilot@JetBlue.COM']))
            ->assertCreated()
            ->assertJsonPath('data.email', 'jane.pilot@jetblue.com');
    }

    public function test_duplicate_email_and_weak_password_are_rejected(): void
    {
        User::factory()->create(['email' => 'jane.pilot@delta.com']);

        $this->postJson('/api/register', $this->registration(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_usernames_are_made_unique(): void
    {
        $this->postJson('/api/register', $this->registration())->assertCreated();
        $this->postJson('/api/logout')->assertNoContent();
        $this->postJson('/api/register', $this->registration(['email' => 'jane2@united.com']))
            ->assertCreated()
            ->assertJsonPath('data.username', 'jane.pilot2');
    }

    public function test_login_and_logout(): void
    {
        User::factory()->create(['email' => 'crew@aa.com', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'secret123'])->assertOk();
        $this->assertAuthenticated();

        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_wrong_password_gives_a_generic_error(): void
    {
        User::factory()->create(['email' => 'crew@aa.com', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email or password is incorrect.');

        $this->postJson('/api/login', ['email' => 'nobody@aa.com', 'password' => 'nope'])
            ->assertJsonPath('errors.email.0', 'Email or password is incorrect.');
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'crew@aa.com', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'secret123'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_logging_in_reopens_a_closed_account(): void
    {
        $user = User::factory()->closed()->create(['email' => 'crew@aa.com', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'secret123'])->assertOk();

        $this->assertNull($user->fresh()->closed_at);
    }

    public function test_protected_routes_require_login(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/feed')->assertUnauthorized();
        $this->postJson('/api/posts', ['body' => 'hi'])->assertUnauthorized();
    }

    public function test_a_closed_account_is_signed_out_everywhere(): void
    {
        $user = User::factory()->closed()->create();

        $this->actingAs($user)->getJson('/api/feed')->assertUnauthorized();
    }

    public function test_spoofed_forwarded_for_header_does_not_bypass_login_throttling(): void
    {
        User::factory()->create(['email' => 'crew@aa.com', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Forwarded-For', "10.0.0.$i")
                ->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'wrong']);
        }

        $this->withHeader('X-Forwarded-For', '10.0.0.99')
            ->postJson('/api/login', ['email' => 'crew@aa.com', 'password' => 'secret123'])
            ->assertStatus(429);
    }
}
