<?php

namespace App\Http\Resources;

use App\Models\NewsAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsAttachment
 */
class NewsAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, type: string, name: string, mime_type: string, size: string, url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->isVideo() ? 'video' : 'image',
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => $this->readable_size,
            'url' => route('news.attachments.show', ['post' => $this->news_post_id, 'attachment' => $this->id]),
        ];
    }
}
