<?php

namespace Database\Factories;

use App\Enums\ForumThreadType;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumThread>
 */
class ForumThreadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'forum_topic_id' => ForumTopic::factory(),
            'type' => fake()->randomElement(ForumThreadType::cases()),
            'body' => fake()->paragraphs(2, true),
        ];
    }

    /**
     * Indicate that the thread is pinned to the top of the forum.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Indicate that the thread no longer accepts replies.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }
}
