<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketListRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketExportController extends Controller
{
    /**
     * Download the tickets a list shows, with the same filters, as a CSV file.
     */
    public function __invoke(TicketListRequest $request): StreamedResponse
    {
        Gate::authorize('export', Ticket::class);

        $user = $request->user();
        $tickets = Ticket::query()
            ->visibleTo($user)
            ->filtered($request->filters(), $user)
            ->with(['requester:'.User::DISPLAY_COLUMNS, 'category:id,name'])
            ->withCount(['supporters', 'comments'])
            ->oldest('id');

        $fileName = 'tickets-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tickets): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Key', 'Type', 'Status', 'Priority', 'Waiting on', 'Subject', 'Category', 'Requester', 'Position', 'Created', 'Last activity', 'Resolved', 'Closed', 'Rating', 'Supporters', 'Messages', 'Shared']);

            foreach ($tickets->lazy(200) as $ticket) {
                fputcsv($out, [
                    $ticket->key,
                    $ticket->type->label(),
                    $ticket->status->label(),
                    $ticket->priority->label(),
                    $ticket->waiting_on?->label() ?? '',
                    $ticket->subject,
                    $ticket->category?->name ?? '',
                    $ticket->requester->name,
                    $ticket->requester->position,
                    $ticket->created_at->toDateTimeString(),
                    $ticket->last_activity_at?->toDateTimeString() ?? '',
                    $ticket->resolved_at?->toDateTimeString() ?? '',
                    $ticket->closed_at?->toDateTimeString() ?? '',
                    $ticket->rating ?? '',
                    $ticket->supporters_count,
                    $ticket->comments_count,
                    $ticket->is_shared ? 'yes' : 'no',
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
