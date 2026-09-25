<?php

namespace App\Http\Resources;

use App\Notifications\ForumCommentReplied;
use App\Notifications\ForumThreadCommented;
use App\Notifications\ForumThreadStarted;
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
                default => 'other',
            },
            'message' => match ($this->type) {
                ForumThreadCommented::class => "{$data['actor']} commented on your thread",
                ForumCommentReplied::class => "{$data['actor']} replied to your comment",
                ForumThreadStarted::class => "{$data['actor']} started a thread",
                default => 'You have a new notification',
            },
            'excerpt' => $data['excerpt'] ?? null,
            'url' => isset($data['thread_id']) ? route('forum.threads.show', $data['thread_id']) : null,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at->shortAbsoluteDiffForHumans(),
        ];
    }
}
