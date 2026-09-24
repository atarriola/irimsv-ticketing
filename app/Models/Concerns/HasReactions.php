<?php

namespace App\Models\Concerns;

use App\Enums\ReactionType;
use App\Models\ForumReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasReactions
{
    /**
     * Get the reactions left on the post.
     *
     * @return HasMany<ForumReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(ForumReaction::class);
    }

    /**
     * Toggle the user's reaction of the given type.
     */
    public function toggleReaction(User $user, ReactionType $type): void
    {
        $existing = $this->reactions()->whereBelongsTo($user)->first();

        if ($existing?->type === $type) {
            $existing->delete();

            return;
        }

        $this->reactions()->updateOrCreate(['user_id' => $user->id], ['type' => $type]);
    }

    /**
     * Summarise the loaded reactions and the given user's own.
     *
     * @return array{counts: array<string, int>, mine: string|null}
     */
    public function reactionSummary(?User $user): array
    {
        return [
            'counts' => $this->reactions->countBy(fn (ForumReaction $reaction): string => $reaction->type->value)->all(),
            'mine' => $this->reactions->firstWhere('user_id', $user?->id)?->type->value,
        ];
    }
}
