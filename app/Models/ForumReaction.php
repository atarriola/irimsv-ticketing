<?php

namespace App\Models;

use App\Enums\ReactionType;
use App\Events\ForumThreadChanged;
use Database\Factories\ForumReactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type'])]
class ForumReaction extends Model
{
    /** @use HasFactory<ForumReactionFactory> */
    use HasFactory;

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        // Everyone reading the thread is told to refresh the reaction counts.
        static::saved(fn (ForumReaction $reaction) => $reaction->announceThreadChanged());
        static::deleted(fn (ForumReaction $reaction) => $reaction->announceThreadChanged());
    }

    /**
     * Tell everyone reading the thread the reaction sits in, whether on the thread itself or on one of its replies.
     */
    protected function announceThreadChanged(): void
    {
        $threadId = $this->forum_thread_id ?? ForumReply::whereKey($this->forum_reply_id)->value('forum_thread_id');

        if ($threadId !== null) {
            ForumThreadChanged::announce($threadId);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReactionType::class,
        ];
    }

    /**
     * Get the thread that was reacted to, when the reaction is on a thread.
     *
     * @return BelongsTo<ForumThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    /**
     * Get the reply that was reacted to, when the reaction is on a reply.
     *
     * @return BelongsTo<ForumReply, $this>
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(ForumReply::class, 'forum_reply_id');
    }

    /**
     * Get the user who reacted.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
