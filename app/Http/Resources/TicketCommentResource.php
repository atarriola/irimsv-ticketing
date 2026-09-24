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
     * @return array{id: int, body: string, author: string, author_is_admin: bool, is_mine: bool, sent_at: string, sent_on: string, can: array{delete: bool}}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author->name,
            'author_is_admin' => $this->author->isAdmin(),
            'is_mine' => $this->user_id === $request->user()->id,
            'sent_at' => $this->created_at->format('g:i A'),
            'sent_on' => $this->created_at->isToday() ? 'Today' : ($this->created_at->isYesterday() ? 'Yesterday' : $this->created_at->toFormattedDateString()),
            'can' => [
                'delete' => $request->user()->can('delete', $this->resource),
            ],
        ];
    }
}
