<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwords_are_stored_with_argon2id_when_configured(): void
    {
        // Docker Compose sets HASH_DRIVER=argon2id; the test environment is cleared, so set it here.
        config(['hashing.driver' => 'argon2id']);
        Hash::forgetDrivers();

        $user = User::factory()->create(['password' => 'flyhigh123']);

        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertTrue(Hash::check('flyhigh123', $user->password));
        $this->assertFalse(Hash::check('wrong', $user->password));
    }
}
