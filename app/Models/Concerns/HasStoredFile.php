<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by the records that each stand for one uploaded file, kept on the disk the model names in its DISK constant.
 *
 * @property string $path
 * @property int $size
 */
trait HasStoredFile
{
    /**
     * Boot the trait: the file goes with its record, so nothing is left behind on disk.
     */
    public static function bootHasStoredFile(): void
    {
        static::deleted(fn (self $file) => Storage::disk(static::DISK)->delete($file->path));
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
}
