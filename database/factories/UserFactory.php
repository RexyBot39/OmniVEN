<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $first = fake()->firstName();

        return [
            'name' => $first.' '.fake()->lastName(),
            'email' => strtolower($first).'.'.Str::random(5).'@39dollarglasses.com',
            'google_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'is_admin' => false,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }
}
