<?php

namespace App\Http\Resources;

use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketAttachment
 */
class TicketAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, size: string, url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size' => $this->readable_size,
            'url' => route('tickets.attachments.show', ['ticket' => $this->ticket_id, 'attachment' => $this->id]),
        ];
    }
}
