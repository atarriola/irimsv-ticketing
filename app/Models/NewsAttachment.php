<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFile;
use Database\Factories\NewsAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path', 'name', 'mime_type', 'size'])]
class NewsAttachment extends Model
{
    /** @use HasFactory<NewsAttachmentFactory> */
    use HasFactory;

    use HasStoredFile;

    /**
     * The disk the files are kept on: a private one, so they are only served to people allowed to read the post.
     */
    public const string DISK = 'local';

    /**
     * The most photos and videos one post can carry.
     */
    public const int MAX_PER_POST = 10;

    /**
     * The largest image accepted, in kilobytes.
     */
    public const int MAX_IMAGE_KILOBYTES = 5120;

    /**
     * The largest video accepted, in kilobytes.
     */
    public const int MAX_VIDEO_KILOBYTES = 51200;

    /**
     * The image formats accepted, as the extensions the validator matches against each file's real content.
     *
     * @var list<string>
     */
    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * The video formats accepted: the two that every current browser can play.
     *
     * @var list<string>
     */
    public const array VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Determine whether the file is a video rather than an image.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Get the post the file goes with.
     *
     * @return BelongsTo<NewsPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class, 'news_post_id');
    }

    /**
     * Scope the query to images.
     *
     * @param  Builder<NewsAttachment>  $query
     * @return Builder<NewsAttachment>
     */
    #[Scope]
    protected function images(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'image/%');
    }
}
