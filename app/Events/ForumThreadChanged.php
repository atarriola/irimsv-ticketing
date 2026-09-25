<?php

namespace App\Events;

use App\Events\Concerns\AnnouncesAfterCommit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Something about a thread or its conversation changed, so everyone reading it should ask the server what.
 *
 * The thread's own readers hear it on the thread's channel; the forum feed hears it too, so a thread shown
 * in the feed can refresh its preview without every feed viewer following every thread.
 */
class ForumThreadChanged implements ShouldBroadcastNow
{
    use AnnouncesAfterCommit, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $threadId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("forum.thread.{$this->threadId}"),
            new PrivateChannel('forum'),
        ];
    }
}
