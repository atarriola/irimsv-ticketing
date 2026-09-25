<?php

namespace App\Notifications;

use App\Models\ForumThread;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Someone started a new thread in the forum; sent to the helpdesk administrators who answer them.
 */
class ForumThreadStarted extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public ForumThread $thread) {}

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
     * @return array{thread_id: int, thread_excerpt: string, actor: string, excerpt: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'thread_id' => $this->thread->id,
            'thread_excerpt' => $this->thread->excerpt,
            'actor' => $this->thread->author->name,
            'excerpt' => $this->thread->excerpt,
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
