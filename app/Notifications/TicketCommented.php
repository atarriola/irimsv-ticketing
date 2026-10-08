<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Notifications\Concerns\DeliversTicketNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Someone left a message on a ticket the recipient raised, follows, or answers for the helpdesk.
 */
class TicketCommented extends Notification implements ShouldQueue
{
    use DeliversTicketNotifications, Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket, public TicketComment $comment) {}

    /**
     * Get the array representation of the notification.
     *
     * @return array{ticket_id: int, ticket_key: string, ticket_type: string, ticket_subject: string, comment_id: int, actor: string, excerpt: string, is_requester: bool}
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
            'is_requester' => $this->ticket->user_id === $notifiable->getKey(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $type = Str::lower($this->ticket->type->label());
        $whose = $this->ticket->user_id === $notifiable->getKey() ? "your {$type}" : "{$type}";

        return (new MailMessage)
            ->subject("[{$this->ticket->key}] {$this->comment->author->name} commented on {$this->ticket->subject}")
            ->line("{$this->comment->author->name} commented on {$whose} {$this->ticket->key}: {$this->ticket->subject}")
            ->line(Str::limit(Str::squish($this->comment->body), 500))
            ->action('Open the conversation', route('tickets.show', $this->ticket));
    }
}
