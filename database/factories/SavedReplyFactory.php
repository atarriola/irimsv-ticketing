<?php

namespace Database\Factories;

use App\Models\SavedReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedReply>
 */
class SavedReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'title' => fake()->words(3, true),
            'body' => fake()->paragraph(),
        ];
    }
}
