<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\TicketStatusChanged;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:close-resolved {--days= : Close tickets resolved at least this many days ago, instead of the configured number}')]
#[Description('Close the tickets that have stayed resolved long enough for the requester to have confirmed the fix')]
class CloseResolvedTickets extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('helpdesk.auto_close_days'));
        $closed = 0;

        Ticket::query()
            ->whereIn('status', [TicketStatus::Resolved, TicketStatus::Shipped])
            ->where('resolved_at', '<=', now()->subDays($days))
            ->with('requester')
            ->eachById(function (Ticket $ticket) use (&$closed): void {
                $ticket->markAs(TicketStatus::Closed);

                rescue(fn () => $ticket->requester->notify(new TicketStatusChanged($ticket, TicketStatus::Closed, null)));

                $closed++;
            });

        $this->info("Closed {$closed} ".($closed === 1 ? 'ticket' : 'tickets')." resolved {$days} or more days ago.");

        return self::SUCCESS;
    }
}
