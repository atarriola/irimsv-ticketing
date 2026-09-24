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
            'category' => $this->category?->name,
            'type' => $this->type->label(),
            'type_key' => $this->type->value,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
