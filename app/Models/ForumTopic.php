<?php

namespace App\Models;

use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'position'])]
class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use HasFactory;

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the threads started in the topic.
     *
     * @return HasMany<ForumThread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }
}
