<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run if the tests would hit anything but the throwaway in-memory database:
     * RefreshDatabase wipes every table.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.$default.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Refusing to run tests against the '$default' database. Tests must use in-memory SQLite.");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests that come from the SPA's own site.
        $this->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json']);
    }

    protected function makeFriends(User $a, User $b): void
    {
        DB::table('friendships')->insert([
            ['user_id' => $a->id, 'friend_id' => $b->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $b->id, 'friend_id' => $a->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
