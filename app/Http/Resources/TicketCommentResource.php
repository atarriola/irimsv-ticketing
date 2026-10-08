<?php

namespace App\Http\Resources;

use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketComment
 */
class TicketCommentResource extends JsonResource
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
            'is_internal' => $this->is_internal,
            'is_edited' => $this->isEdited(),
            'author' => $this->author->name,
            'author_position' => $this->author->position,
            'author_photo_url' => $this->author->photo_url,
            'author_is_admin' => $this->author->isAdmin(),
            'is_mine' => $this->user_id === $request->user()->id,
            'sent_at' => $this->created_at->format('g:i A'),
            'sent_on' => $this->created_at->isToday() ? 'Today' : ($this->created_at->isYesterday() ? 'Yesterday' : $this->created_at->toFormattedDateString()),
            'attachments' => $this->whenLoaded('attachments', fn (): array => TicketAttachmentResource::collection($this->attachments)->resolve($request)),
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ],
        ];
    }
}
