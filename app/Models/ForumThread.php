<?php

namespace App\Models;

use App\Enums\ForumThreadType;
use App\Events\ForumThreadChanged;
use App\Events\ForumThreadPosted;
use App\Models\Concerns\HasReactions;
use Database\Factories\ForumThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['forum_topic_id', 'type', 'body'])]
class ForumThread extends Model
{
    /** @use HasFactory<ForumThreadFactory> */
    use HasFactory, HasReactions;

    /**
     * The number of comments previewed under a thread in the feed.
     */
    public const int PREVIEW_COMMENTS = 2;

    /**
     * The number of comments loaded each time more of a conversation is requested.
     */
    public const int COMMENTS_PER_PAGE = 20;

    /**
     * The relationships a comment needs loaded before it is sent to the client.
     *
     * @var list<string>
     */
    public const array COMMENT_RELATIONS = [
        'author:'.User::DISPLAY_COLUMNS,
        'reactions',
        'children.author:'.User::DISPLAY_COLUMNS,
        'children.reactions',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_pinned' => false,
        'is_locked' => false,
    ];

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::creating(function (ForumThread $thread): void {
            $thread->last_activity_at ??= now();
        });

        // The feed is told about a new thread, and a thread's readers about anything that changes what they see.
        // Recording activity after a reply is left out, because the reply announces itself.
        static::created(fn (ForumThread $thread) => ForumThreadPosted::announce($thread->id));
        static::updated(function (ForumThread $thread): void {
            if ($thread->wasChanged(['forum_topic_id', 'type', 'body', 'is_pinned', 'is_locked'])) {
                ForumThreadChanged::announce($thread->id);
            }
        });
        static::deleted(fn (ForumThread $thread) => ForumThreadChanged::announce($thread->id));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ForumThreadType::class,
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get a short, single-line preview of the message for lists and page titles.
     *
     * @return Attribute<string, never>
     */
    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => Str::limit(Str::squish($this->body), 80));
    }

    /**
     * Get the topic the thread was started in.
     *
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    /**
     * Get the user who started the thread.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the replies posted in the thread.
     *
     * @return HasMany<ForumReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class);
    }

    /**
     * Get the top-level comments on the thread, oldest first.
     *
     * @return HasMany<ForumReply, $this>
     */
    public function comments(): HasMany
    {
        return $this->replies()->whereNull('parent_id')->oldest()->oldest('id');
    }

    /**
     * Get the first few comments, shown under the thread in the feed.
     *
     * @return HasMany<ForumReply, $this>
     */
    public function previewComments(): HasMany
    {
        return $this->comments()->limit(self::PREVIEW_COMMENTS);
    }

    /**
     * Scope the query to list pinned threads first, then the most recently active.
     *
     * @param  Builder<ForumThread>  $query
     * @return Builder<ForumThread>
     */
    #[Scope]
    protected function mostRecentlyActive(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('last_activity_at')->orderByDesc('id');
    }

    /**
     * Mark the thread as active right now so it rises in its topic.
     */
    public function recordActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Count the thread's replies at every level, and its top-level comments.
     *
     * @return array{replies_count: int, comments_count: int}
     */
    public function conversationTotals(): array
    {
        return [
            'replies_count' => $this->replies()->count(),
            'comments_count' => $this->comments()->reorder()->count(),
        ];
    }
}
