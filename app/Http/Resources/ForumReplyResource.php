<?php

namespace App\Http\Resources;

use App\Models\ForumReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumReply
 */
class ForumReplyResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'author' => $this->author->name,
            'author_position' => $this->author->position,
            'author_photo_url' => $this->author->photo_url,
            'author_is_admin' => $this->author->isAdmin(),
            'created_at' => $this->created_at->gt(now()->subMinute()) ? 'Just now' : $this->created_at->shortAbsoluteDiffForHumans(),
            'reactions' => $this->whenLoaded('reactions', fn (): array => $this->reactionSummary($request->user())),
            'children' => $this->whenLoaded('children', fn (): array => $this->children
                ->map(fn (ForumReply $child): array => self::make($child)->resolve($request))
                ->all()),
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ],
        ];
    }
}
