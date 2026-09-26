<?php

namespace App\Models;

use App\Enums\NewsKind;
use Database\Factories\NewsPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

#[Fillable(['kind', 'title', 'body', 'published_at'])]
class NewsPost extends Model
{
    /** @use HasFactory<NewsPostFactory> */
    use HasFactory;

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        // Attachments are deleted one by one, so each one's file is removed from disk too.
        static::deleting(fn (NewsPost $post) => $post->attachments()->get()->each->delete());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NewsKind::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get a short, single-line preview of the body for lists.
     *
     * @return Attribute<string, never>
     */
    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => Str::limit(Str::squish($this->body), 180));
    }

    /**
     * Determine whether readers can see the post, rather than only administrators.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Get the administrator who wrote the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the photos and videos that go with the post, in the order they were added.
     *
     * @return HasMany<NewsAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(NewsAttachment::class)->oldest('id');
    }

    /**
     * Get the first photo added to the post, shown as its cover in lists.
     *
     * @return HasOne<NewsAttachment, $this>
     */
    public function coverImage(): HasOne
    {
        return $this->hasOne(NewsAttachment::class)->ofMany(['id' => 'min'], fn (Builder $query) => $query->images());
    }

    /**
     * Keep an uploaded photo or video with the post, on the attachments disk.
     */
    public function addAttachment(UploadedFile $file): NewsAttachment
    {
        return $this->attachments()->create([
            'path' => $file->store("news-attachments/{$this->id}", NewsAttachment::DISK),
            'name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Scope the query to posts that have been published.
     *
     * @param  Builder<NewsPost>  $query
     * @return Builder<NewsPost>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope the query to the posts the user may read: everything for an administrator, published posts for anyone else.
     *
     * @param  Builder<NewsPost>  $query
     * @return Builder<NewsPost>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->published();
    }

    /**
     * Scope the query to list drafts first, then the most recently published posts.
     *
     * @param  Builder<NewsPost>  $query
     * @return Builder<NewsPost>
     */
    #[Scope]
    protected function newestFirst(Builder $query): Builder
    {
        return $query->orderByRaw('published_at is null desc')->orderByDesc('published_at')->orderByDesc('id');
    }
}
