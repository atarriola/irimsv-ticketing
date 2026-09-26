<?php

namespace App\Http\Resources;

use App\Models\NewsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsPost
 */
class NewsPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'author' => $this->author->name,
            'author_photo_url' => $this->author->photo_url,
            'author_is_admin' => $this->author->isAdmin(),
            'is_published' => $this->isPublished(),
            'published_on' => $this->published_at?->toFormattedDateString(),
            'updated_at' => $this->updated_at->diffForHumans(),
        ];
    }
}
