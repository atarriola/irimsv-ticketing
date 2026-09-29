<?php

namespace App\Notifications;

use App\Models\ForumReaction;
use App\Models\ForumReply;
use App\Models\ForumThread;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone reacted to a comment the recipient wrote.
 */
class ForumCommentReacted extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public ForumThread $thread, public ForumReply $reply, public ForumReaction $reaction) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{thread_id: int, thread_excerpt: string, reply_id: int, actor: string, reaction: string, excerpt: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'thread_id' => $this->thread->id,
            'thread_excerpt' => $this->thread->excerpt,
            'reply_id' => $this->reply->id,
            'actor' => $this->reaction->user->name,
            'reaction' => $this->reaction->type->value,
            'excerpt' => Str::limit(Str::squish($this->reply->body), 80),
        ];
    }

    /**
     * Get the broadcastable representation of the notification, sent inside the request rather than from the queue.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
