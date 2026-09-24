<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
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
            'category_id' => Category::factory(),
            'type' => fake()->randomElement(TicketType::cases()),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
        ];
    }

    /**
     * Indicate that the ticket is a feature request.
     */
    public function featureRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TicketType::FeatureRequest,
        ]);
    }

    /**
     * Indicate that the ticket is being worked on.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::InProgress,
        ]);
    }

    /**
     * Indicate that the ticket has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Indicate that the ticket has been closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
