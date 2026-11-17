<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'user'.fake()->unique()->numerify('####'),
            'job_title' => fake()->jobTitle(),
            'role' => 'STAFF',
            'pin' => '1234',
            'al_balance' => 12,
            'dp_balance' => 0,
            'is_active' => true,
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'MANAGER',
            'job_title' => 'IT Manager',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
