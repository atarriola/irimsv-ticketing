<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'type', 'priority', 'subject', 'description'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * The prefix of every ticket key.
     */
    public const string KEY_PREFIX = 'TKT';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => TicketPriority::Medium->value,
        'status' => TicketStatus::Open->value,
    ];

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        // Attachments are deleted one by one, so each one's file is removed from disk too.
        static::deleting(fn (Ticket $ticket) => $ticket->attachments()->get()->each->delete());
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
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
     * Get the comments posted on the ticket.
     *
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Get the images attached to the ticket, oldest first.
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->oldest('id');
    }

    /**
     * Keep an uploaded image with the ticket, on the attachments disk.
     */
    public function addAttachment(UploadedFile $file, User $uploader): TicketAttachment
    {
        return $this->attachments()->create([
            'user_id' => $uploader->id,
            'path' => $file->store("ticket-attachments/{$this->id}", TicketAttachment::DISK),
            'name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
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
     * Get the ticket's human-friendly key, such as TKT-12.
     *
     * @return Attribute<string, never>
     */
    protected function key(): Attribute
    {
        return Attribute::get(fn (): string => self::KEY_PREFIX.'-'.$this->id);
    }

    /**
     * Scope the query to tickets matching a key, number or words.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $number = Str::of($term)->trim()->lower()->after(Str::lower(self::KEY_PREFIX).'-')->toString();
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($number, $pattern): void {
            $query->whereLike('subject', $pattern)
                ->orWhereLike('description', $pattern)
                ->when(ctype_digit($number), fn (Builder $query) => $query->orWhere('id', (int) $number));
        });
    }

    /**
     * Scope the query to the tickets the user is allowed to see.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereBelongsTo($user, 'requester');
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
     * Move the ticket to the given status and keep its resolution timestamps in sync.
     */
    public function markAs(TicketStatus $status): void
    {
        $this->status = $status;

        if ($status->isActive()) {
            $this->resolved_at = null;
            $this->closed_at = null;
        } elseif ($status === TicketStatus::Resolved) {
            $this->resolved_at = now();
            $this->closed_at = null;
        } else {
            $this->closed_at = now();
        }

        $this->save();
    }
}
