<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone left a message on a ticket the recipient raised.
 */
class TicketCommented extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket, public TicketComment $comment) {}

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
     * @return array{ticket_id: int, ticket_key: string, ticket_type: string, ticket_subject: string, comment_id: int, actor: string, excerpt: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_key' => $this->ticket->key,
            'ticket_type' => Str::lower($this->ticket->type->label()),
            'ticket_subject' => $this->ticket->subject,
            'comment_id' => $this->comment->id,
            'actor' => $this->comment->author->name,
            'excerpt' => Str::limit(Str::squish($this->comment->body), 80),
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
