<?php

namespace App\Models;

use App\Models\Concerns\HasReactions;
use Database\Factories\ForumReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'parent_id', 'body'])]
class ForumReply extends Model
{
    /** @use HasFactory<ForumReplyFactory> */
    use HasFactory, HasReactions;

    /**
     * Get the thread the reply belongs to.
     *
     * @return BelongsTo<ForumThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    /**
     * Get the user who wrote the reply.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the comment this reply answers, when it is not a comment on the thread itself.
     *
     * @return BelongsTo<ForumReply, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the replies that answer this comment, oldest first.
     *
     * @return HasMany<ForumReply, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest()->oldest('id');
    }
}
