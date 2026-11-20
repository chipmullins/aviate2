<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();
        $domain = fake()->randomElement(config('aviate.allowed_email_domains'));

        return [
            'first_name' => $first,
            'last_name' => $last,
            'username' => Str::lower(Str::ascii($first.'.'.$last)).fake()->unique()->numberBetween(1, 999999),
            'email' => fake()->unique()->userName().'@'.$domain,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['closed_at' => now()]);
    }
}
