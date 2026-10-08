<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
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
            'key' => $this->key,
            'subject' => $this->subject,
            'requester' => $this->requester->name,
            'requester_position' => $this->requester->position,
            'category' => $this->category?->name,
            'type' => $this->type->label(),
            'type_key' => $this->type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'waiting_on' => $this->waiting_on?->value,
            'waiting_label' => $this->waiting_on?->label(),
            'is_shared' => $this->is_shared,
            'is_deleted' => $this->trashed(),
            'rating' => $this->rating,
            'comments_count' => $this->whenCounted('comments'),
            'supporters_count' => $this->whenCounted('supporters'),
            'created_at' => $this->created_at->diffForHumans(),
            'last_activity_at' => $this->last_activity_at?->diffForHumans(),
        ];
    }
}
