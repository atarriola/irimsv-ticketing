<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\DeliversTicketNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Someone raised a ticket; sent to the helpdesk administrators who answer them.
 */
class TicketRaised extends Notification implements ShouldQueue
{
    use DeliversTicketNotifications, Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket) {}

    /**
     * Get the array representation of the notification.
     *
     * @return array{ticket_id: int, ticket_key: string, ticket_type: string, ticket_subject: string, actor: string, excerpt: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_key' => $this->ticket->key,
            'ticket_type' => Str::lower($this->ticket->type->label()),
            'ticket_subject' => $this->ticket->subject,
            'actor' => $this->ticket->requester->name,
            'excerpt' => Str::limit(Str::squish($this->ticket->subject), 80),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $type = Str::lower($this->ticket->type->label());

        return (new MailMessage)
            ->subject("[{$this->ticket->key}] New {$type}: {$this->ticket->subject}")
            ->line("{$this->ticket->requester->name} raised a {$type}: {$this->ticket->subject}")
            ->line(Str::limit(Str::squish($this->ticket->description), 300))
            ->action('Open the ticket', route('tickets.show', $this->ticket));
    }
}
