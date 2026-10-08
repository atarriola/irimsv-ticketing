<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * How every ticket notification reaches people: the bell straight away, and an email for those who want one.
 *
 * The database and broadcast channels run inside the request, so the bell rings within milliseconds and
 * nothing depends on a queue worker. Only the email goes through the queue, as sending it is slow.
 */
trait DeliversTicketNotifications
{
    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('helpdesk.email_notifications') && $notifiable instanceof User && $notifiable->wantsEmailNotifications()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the queue connection for each channel: the bell's channels run at once, the email waits its turn.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'broadcast' => 'sync',
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
