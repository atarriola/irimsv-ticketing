<?php

namespace App\Http\Resources;

use App\Notifications\ForumCommentReplied;
use App\Notifications\ForumThreadCommented;
use App\Notifications\ForumThreadStarted;
use App\Notifications\TicketCommented;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'kind' => match ($this->type) {
                ForumThreadCommented::class => 'comment',
                ForumCommentReplied::class => 'reply',
                ForumThreadStarted::class => 'thread',
                TicketCommented::class => 'ticket',
                default => 'other',
            },
            'message' => match ($this->type) {
                ForumThreadCommented::class => "{$data['actor']} commented on your thread",
                ForumCommentReplied::class => "{$data['actor']} replied to your comment",
                ForumThreadStarted::class => "{$data['actor']} started a thread",
                TicketCommented::class => "{$data['actor']} commented on your {$data['ticket_type']} {$data['ticket_key']}",
                default => 'You have a new notification',
            },
            'excerpt' => $data['excerpt'] ?? null,
            'url' => match (true) {
                isset($data['ticket_id']) => route('tickets.show', $data['ticket_id']),
                isset($data['thread_id']) => route('forum.threads.show', $data['thread_id']),
                default => null,
            },
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at->shortAbsoluteDiffForHumans(),
        ];
    }
}
