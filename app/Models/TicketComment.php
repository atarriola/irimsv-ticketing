<?php

namespace App\Models;

use App\Events\TicketConversationChanged;
use Database\Factories\TicketCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

#[Fillable(['user_id', 'body', 'is_internal'])]
class TicketComment extends Model
{
    /** @use HasFactory<TicketCommentFactory> */
    use HasFactory;

    /**
     * The most images one message can carry.
     */
    public const int MAX_IMAGES = 3;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_internal' => false,
    ];

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        // A message anyone can see moves the ticket along: it rises on the board and the turn passes.
        // An internal note changes nothing the requester would notice.
        static::created(function (TicketComment $comment): void {
            if (! $comment->is_internal) {
                $comment->ticket->refreshWaitingOn();
                $comment->ticket->recordActivity();
            }
        });

        // The images are removed one by one before the row goes, so each one's file is taken off the disk too.
        static::deleting(fn (TicketComment $comment) => $comment->attachments()->get()->each->delete());

        static::deleted(function (TicketComment $comment): void {
            if (! $comment->is_internal) {
                $comment->ticket->refreshWaitingOn();
            }
        });

        // Everyone reading the ticket is told to refresh their copy of the conversation.
        static::saved(fn (TicketComment $comment) => TicketConversationChanged::announce($comment->ticket_id));
        static::deleted(fn (TicketComment $comment) => TicketConversationChanged::announce($comment->ticket_id));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Get the ticket the comment belongs to.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who wrote the comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the images sent with the message, oldest first.
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->oldest('id');
    }

    /**
     * Keep an uploaded image with the message.
     */
    public function addAttachment(UploadedFile $file, User $uploader): TicketAttachment
    {
        return $this->ticket->addAttachment($file, $uploader, $this);
    }

    /**
     * Determine whether the message was changed after it was sent.
     */
    public function isEdited(): bool
    {
        return $this->updated_at->gt($this->created_at->addSeconds(2));
    }

    /**
     * Scope the query to the messages the user may read: internal notes are for administrators only.
     *
     * @param  Builder<TicketComment>  $query
     * @return Builder<TicketComment>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('is_internal', false);
    }
}
