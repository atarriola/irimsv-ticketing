<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketWatcher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketWatcher>
 */
class TicketWatcherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'is_affected' => false,
        ];
    }

    /**
     * Indicate that the watcher has the same problem, or wants the feature too.
     */
    public function affected(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_affected' => true,
        ]);
    }
}
