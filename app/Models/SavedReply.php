<?php

namespace App\Models;

use Database\Factories\SavedReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message the helpdesk sends often, shared by every administrator.
 */
#[Fillable(['user_id', 'title', 'body'])]
class SavedReply extends Model
{
    /** @use HasFactory<SavedReplyFactory> */
    use HasFactory;

    /**
     * Get the administrator who wrote the reply.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
