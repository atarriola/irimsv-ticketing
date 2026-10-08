<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SimilarTicketController extends Controller
{
    /**
     * The fewest characters worth searching for.
     */
    private const int MIN_TERM_LENGTH = 3;

    /**
     * The most matches suggested.
     */
    private const int LIMIT = 5;

    /**
     * Suggest the tickets the user may read that look like the one they are about to raise.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $term = Str::limit(trim((string) $request->string('q')), 100, '');

        if (Str::length($term) < self::MIN_TERM_LENGTH) {
            return response()->json(['data' => []]);
        }

        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->search($term)
            ->mostRecentlyActive()
            ->limit(self::LIMIT)
            ->get(['id', 'subject', 'status', 'type']);

        return response()->json([
            'data' => $tickets->map(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'key' => $ticket->key,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'url' => route('tickets.show', $ticket),
            ]),
        ]);
    }
}
