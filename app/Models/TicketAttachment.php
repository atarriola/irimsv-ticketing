<?php

namespace App\Models;

use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'path', 'name', 'mime_type', 'size'])]
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

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
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        // The file goes with its record, so nothing is left behind on disk.
        static::deleted(fn (TicketAttachment $attachment) => Storage::disk(self::DISK)->delete($attachment->path));
    }

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
     * Get the file size in kilobytes or megabytes, for display.
     *
     * @return Attribute<string, never>
     */
    protected function readableSize(): Attribute
    {
        return Attribute::get(fn (): string => $this->size >= 1024 * 1024
            ? round($this->size / (1024 * 1024), 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB');
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
