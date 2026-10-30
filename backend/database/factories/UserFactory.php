<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'role' => UserRole::Rider,
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    public function rider(): static
    {
        return $this->state(['role' => UserRole::Rider]);
    }

    public function driver(): static
    {
        return $this->state(['role' => UserRole::Driver]);
    }
}
