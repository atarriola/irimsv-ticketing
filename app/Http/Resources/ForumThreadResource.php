<?php

namespace App\Http\Resources;

use App\Models\ForumReply;
use App\Models\ForumThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumThread
 */
class ForumThreadResource extends JsonResource
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
            'body' => $this->body,
            'excerpt' => $this->excerpt,
            'type' => $this->type->label(),
            'topic' => $this->topic->only(['name', 'slug']),
            'author' => $this->author->name,
            'author_position' => $this->author->position,
            'author_photo_url' => $this->author->photo_url,
            'author_is_admin' => $this->author->isAdmin(),
            'replies_count' => $this->replies_count,
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'created_at' => $this->created_at->diffForHumans(),
            'comments_count' => $this->whenCounted('comments'),
            'reactions' => $this->whenLoaded('reactions', fn (): array => $this->reactionSummary($request->user())),
            'preview_comments' => $this->whenLoaded('previewComments', fn (): array => $this->previewComments
                ->map(fn (ForumReply $comment): array => ForumReplyResource::make($comment)->resolve($request))
                ->all()),
            'can' => [
                'reply' => $request->user()->can('reply', $this->resource),
            ],
        ];
    }
}
