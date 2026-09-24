<?php

namespace Database\Factories;

use App\Models\Usertype;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usertype>
 */
class UsertypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type_name' => fake()->unique()->jobTitle(),
            'level' => fake()->numberBetween(1, 4),
        ];
    }

    /**
     * Indicate that this is the LRMIS Administrator type.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_name' => 'Administrator',
            'level' => Usertype::ADMINISTRATOR_LEVEL,
        ]);
    }
}
