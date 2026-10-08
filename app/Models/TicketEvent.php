<?php

namespace App\Models;

use App\Enums\TicketEventKind;
use Database\Factories\TicketEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a ticket's timeline. Messages are not events: they are the conversation.
 */
#[Fillable(['user_id', 'kind', 'details'])]
class TicketEvent extends Model
{
    /** @use HasFactory<TicketEventFactory> */
    use HasFactory;

    /**
     * An event never changes, so it has no updated_at column.
     */
    public const null UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TicketEventKind::class,
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the ticket the event happened to.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who did it, or null when the system did.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
