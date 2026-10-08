<?php

namespace App\Events;

use App\Events\Concerns\AnnouncesAfterCommit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket or its conversation changed, so everyone reading it should ask the server what.
 */
class TicketConversationChanged implements ShouldBroadcastNow
{
    use AnnouncesAfterCommit, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $ticketId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("ticket.{$this->ticketId}")];
    }
}
