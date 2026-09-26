<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
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
            'path' => 'ticket-attachments/'.fake()->uuid().'.png',
            'name' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(10_000, 2_000_000),
        ];
    }
}
