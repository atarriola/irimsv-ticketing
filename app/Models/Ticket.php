<?php

namespace App\Models;

use App\Enums\TicketEventKind;
use App\Enums\TicketPriority;
use App\Enums\TicketSort;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\WaitingOn;
use App\Events\TicketConversationChanged;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'type', 'priority', 'subject', 'description', 'is_shared', 'forum_thread_id'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, Prunable, SoftDeletes;

    /**
     * The prefix of every ticket key.
     */
    public const string KEY_PREFIX = 'TKT';

    /**
     * How long a deleted ticket can still be restored before it is removed for good.
     */
    public const int DAYS_KEPT_AFTER_DELETION = 30;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => TicketPriority::Medium->value,
        'status' => TicketStatus::Open->value,
        'is_shared' => false,
    ];

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->last_activity_at ??= now();
            $ticket->waiting_on ??= match (true) {
                $ticket->status->isResolution() => WaitingOn::Requester,
                $ticket->status->isActive() => WaitingOn::Support,
                default => null,
            };
        });

        // The files only go when the ticket is gone for good; a deleted ticket can still be restored with them.
        static::deleting(function (Ticket $ticket): void {
            if ($ticket->isForceDeleting()) {
                $ticket->attachments()->get()->each->delete();
            }
        });

        // Everyone reading the ticket is told when something they can see has changed.
        static::updated(function (Ticket $ticket): void {
            if ($ticket->wasChanged(['status', 'priority', 'subject', 'description', 'is_shared', 'deleted_at', 'news_post_id'])) {
                TicketConversationChanged::announce($ticket->id);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'waiting_on' => WaitingOn::class,
            'is_shared' => 'boolean',
            'rating' => 'integer',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the prunable model query: tickets deleted long enough ago to be removed for good.
     *
     * @return Builder<Ticket>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::DAYS_KEPT_AFTER_DELETION));
    }

    /**
     * Get the user who raised the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the category the ticket is filed under.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the news post announcing the release that delivered this feature request.
     *
     * @return BelongsTo<NewsPost, $this>
     */
    public function newsPost(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class);
    }

    /**
     * Get the forum thread the ticket was raised from, if any.
     *
     * @return BelongsTo<ForumThread, $this>
     */
    public function forumThread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class);
    }

    /**
     * Get the comments posted on the ticket.
     *
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Get every file on the ticket, whether sent with the ticket or with a message, oldest first.
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->oldest('id');
    }

    /**
     * Get the images sent with the ticket itself, oldest first.
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function screenshots(): HasMany
    {
        return $this->attachments()->whereNull('ticket_comment_id');
    }

    /**
     * Get the ticket's timeline, oldest first.
     *
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->oldest('id');
    }

    /**
     * Get the users following the ticket, with whether each is affected by it too.
     *
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_watchers')->using(TicketWatcher::class)->withPivot('is_affected')->withTimestamps();
    }

    /**
     * Get the followers who have the same problem, or who want the feature too.
     *
     * @return BelongsToMany<User, $this>
     */
    public function supporters(): BelongsToMany
    {
        return $this->watchers()->wherePivot('is_affected', true);
    }

    /**
     * Get the people who follow the ticket's progress: the requester and everyone watching it.
     *
     * @return Collection<int, User>
     */
    public function audience(): Collection
    {
        return $this->watchers()->get()->push($this->requester)->unique('id')->values();
    }

    /**
     * Keep an uploaded image with the ticket, on the attachments disk, filed under a message when sent with one.
     */
    public function addAttachment(UploadedFile $file, User $uploader, ?TicketComment $comment = null): TicketAttachment
    {
        return $this->attachments()->create([
            'user_id' => $uploader->id,
            'ticket_comment_id' => $comment?->id,
            'path' => $file->store("ticket-attachments/{$this->id}", TicketAttachment::DISK),
            'name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Add an entry to the ticket's timeline. A system action, such as closing a stale ticket, has no user.
     *
     * @param  array<string, mixed>  $details
     */
    public function recordEvent(TicketEventKind $kind, ?User $user, array $details = []): TicketEvent
    {
        return $this->events()->create([
            'user_id' => $user?->id,
            'kind' => $kind,
            'details' => $details === [] ? null : $details,
        ]);
    }

    /**
     * Mark the ticket as active right now so it rises on the board.
     */
    public function recordActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Work out whose turn it is from the conversation and the status, and remember it.
     *
     * An active ticket waits on the helpdesk until an administrator writes, and on the requester
     * after that. A resolved ticket waits for the requester to confirm. A closed one waits on nobody.
     */
    public function refreshWaitingOn(): void
    {
        $this->waiting_on = $this->waitingOnNow();
        $this->save();
    }

    /**
     * Move the ticket to the given status, keep its timestamps and turn in sync, and note who did it.
     */
    public function markAs(TicketStatus $status, ?User $actor = null): void
    {
        $previous = $this->status;

        $this->status = $status;

        if ($status->isActive()) {
            $this->resolved_at = null;
            $this->closed_at = null;
        } elseif ($status->isResolution()) {
            $this->resolved_at = now();
            $this->closed_at = null;
        } else {
            $this->closed_at = now();
        }

        $this->waiting_on = $this->waitingOnNow();
        $this->last_activity_at = now();
        $this->save();

        if ($previous !== $status) {
            $this->recordEvent(TicketEventKind::StatusChanged, $actor, ['from' => $previous->value, 'to' => $status->value]);
        }
    }

    /**
     * Change the ticket's priority and note who did it.
     */
    public function changePriority(TicketPriority $priority, ?User $actor = null): void
    {
        $previous = $this->priority;

        if ($previous === $priority) {
            return;
        }

        $this->priority = $priority;
        $this->last_activity_at = now();
        $this->save();

        $this->recordEvent(TicketEventKind::PriorityChanged, $actor, ['from' => $previous->value, 'to' => $priority->value]);
    }

    /**
     * Determine whether the requester may still confirm or reopen the ticket after its resolution.
     */
    public function isAwaitingConfirmation(): bool
    {
        return $this->status->isResolution()
            && $this->resolved_at !== null
            && $this->resolved_at->gte(now()->subDays((int) config('helpdesk.reopen_window_days')));
    }

    /**
     * Determine whether the ticket is done, so the requester may say how the help was.
     */
    public function canBeRated(): bool
    {
        return ! $this->status->isActive();
    }

    /**
     * Get the ticket's human-friendly key, such as TKT-12.
     *
     * @return Attribute<string, never>
     */
    protected function key(): Attribute
    {
        return Attribute::get(fn (): string => self::KEY_PREFIX.'-'.$this->id);
    }

    /**
     * Scope the query to tickets that still need attention.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('status', TicketStatus::active());
    }

    /**
     * Scope the query to the tickets the user may read: every ticket for an administrator,
     * their own and the shared ones for anyone else.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhere('is_shared', true));
    }

    /**
     * Scope the query to the tickets the user raised.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function raisedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope the query to tickets matching a key, number, words, or the requester's name.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $number = Str::of($term)->trim()->lower()->after(Str::lower(self::KEY_PREFIX).'-')->toString();
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($number, $pattern, $term): void {
            $query->whereLike('subject', $pattern)
                ->orWhereLike('description', $pattern)
                ->orWhereHas('requester', fn (Builder $query) => $query->search($term))
                ->when(ctype_digit($number), fn (Builder $query) => $query->orWhere('id', (int) $number));
        });
    }

    /**
     * Scope the query to list the most urgent tickets first.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function mostUrgentFirst(Builder $query): Builder
    {
        $weights = collect(TicketPriority::cases())
            ->map(fn (TicketPriority $priority): string => "when '{$priority->value}' then {$priority->weight()}")
            ->implode(' ');

        return $query->orderByRaw("case priority {$weights} end desc");
    }

    /**
     * Scope the query to list the most recently active tickets first.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function mostRecentlyActive(Builder $query): Builder
    {
        return $query->orderByDesc('last_activity_at')->orderByDesc('id');
    }

    /**
     * Scope the query to the order a list asked for. Sorting by support needs the supporters counted first.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function sortedBy(Builder $query, TicketSort $sort): Builder
    {
        return match ($sort) {
            TicketSort::Newest => $query->latest()->latest('id'),
            TicketSort::Oldest => $query->oldest()->oldest('id'),
            TicketSort::Active => $query->mostRecentlyActive(),
            TicketSort::Priority => $query->mostUrgentFirst()->mostRecentlyActive(),
            TicketSort::Votes => $query->orderByDesc('supporters_count')->mostRecentlyActive(),
        };
    }

    /**
     * Scope the query to the tickets a list or export asked for.
     *
     * @param  Builder<Ticket>  $query
     * @param  array{types: list<TicketType>, status: TicketStatus|null, priority: TicketPriority|null, category: int|null, waiting: WaitingOn|null, mine: bool, trashed: bool, q: string}  $filters
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters, User $user): Builder
    {
        return $query
            ->whereIn('type', $filters['types'])
            ->when($filters['trashed'], fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['mine'], fn (Builder $query) => $query->raisedBy($user))
            ->when($filters['status'], fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['priority'], fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when($filters['category'], fn (Builder $query) => $query->where('category_id', $filters['category']))
            ->when($filters['waiting'], fn (Builder $query) => $query->where('waiting_on', $filters['waiting']))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->search($filters['q']));
    }

    /**
     * Whose turn it is right now, given the status and the last message anyone can see.
     */
    private function waitingOnNow(): ?WaitingOn
    {
        if ($this->status->isResolution()) {
            return WaitingOn::Requester;
        }

        if (! $this->status->isActive()) {
            return null;
        }

        $lastMessage = $this->comments()->where('is_internal', false)->with('author:'.User::DISPLAY_COLUMNS)->latest('id')->first();

        return $lastMessage?->author->isAdmin() ? WaitingOn::Requester : WaitingOn::Support;
    }
}
