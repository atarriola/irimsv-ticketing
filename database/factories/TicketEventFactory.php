<?php

namespace Database\Factories;

use App\Enums\TicketEventKind;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketEvent>
 */
class TicketEventFactory extends Factory
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
            'kind' => TicketEventKind::StatusChanged,
            'details' => ['from' => 'open', 'to' => 'in_progress'],
        ];
    }
}
