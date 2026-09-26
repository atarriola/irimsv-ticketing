<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFile;
use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'path', 'name', 'mime_type', 'size'])]
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

    use HasStoredFile;

    /**
     * The disk the files are kept on: a private one, so they are only served to people allowed to see the ticket.
     */
    public const string DISK = 'local';

    /**
     * The most images one ticket can carry.
     */
    public const int MAX_PER_TICKET = 5;

    /**
     * The largest image accepted, in kilobytes.
     */
    public const int MAX_KILOBYTES = 5120;

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
     * Get the ticket the file is attached to.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
