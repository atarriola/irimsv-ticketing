<?php

namespace Database\Factories;

use App\Enums\ReactionType;
use App\Models\ForumReaction;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReaction>
 */
class ForumReactionFactory extends Factory
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
            'forum_thread_id' => ForumThread::factory(),
            'type' => ReactionType::Heart,
        ];
    }
}
