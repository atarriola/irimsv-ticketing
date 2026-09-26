<?php

namespace Database\Factories;

use App\Enums\NewsKind;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
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
            'kind' => fake()->randomElement(NewsKind::cases()),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(3, true),
            'published_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }

    /**
     * Indicate that the post is still being written and is not shown to readers yet.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
}
