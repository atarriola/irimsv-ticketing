<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Concerns\DeliversTicketNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A ticket the recipient raised or follows was moved to another status, by someone or by the helpdesk itself.
 */
class TicketStatusChanged extends Notification implements ShouldQueue
{
    use DeliversTicketNotifications, Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket, public TicketStatus $status, public ?User $actor) {}

    /**
     * Get the array representation of the notification.
     *
     * @return array{ticket_id: int, ticket_key: string, ticket_type: string, ticket_subject: string, status: string, status_label: string, actor: string|null, excerpt: string, is_requester: bool}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_key' => $this->ticket->key,
            'ticket_type' => Str::lower($this->ticket->type->label()),
            'ticket_subject' => $this->ticket->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'actor' => $this->actor?->name,
            'excerpt' => Str::limit(Str::squish($this->ticket->subject), 80),
            'is_requester' => $this->ticket->user_id === $notifiable->getKey(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $type = Str::lower($this->ticket->type->label());
        $actor = $this->actor?->name ?? 'The helpdesk';
        $whose = $this->ticket->user_id === $notifiable->getKey() ? "your {$type}" : "{$type}";

        $message = (new MailMessage)
            ->subject("[{$this->ticket->key}] {$this->status->label()}: {$this->ticket->subject}")
            ->line("{$actor} marked {$whose} {$this->ticket->key} as {$this->status->label()}: {$this->ticket->subject}");

        if ($this->status->isResolution() && $this->ticket->user_id === $notifiable->getKey()) {
            $message->line('If the problem is not fixed for you, you can reopen the ticket from its page.');
        }

        return $message->action('Open the ticket', route('tickets.show', $this->ticket));
    }
}
