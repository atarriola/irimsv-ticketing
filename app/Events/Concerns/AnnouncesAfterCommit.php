<?php

namespace App\Events\Concerns;

use Illuminate\Support\Facades\DB;

trait AnnouncesAfterCommit
{
    /**
     * Broadcast the event as soon as the surrounding database transaction, if any, has committed.
     *
     * The broadcast happens inside the request rather than on the queue, so readers hear about a change within
     * milliseconds. Failing to reach the WebSocket server is reported and otherwise ignored: the request must still
     * succeed, and the pages resync on their own.
     */
    public static function announce(int $threadId): void
    {
        DB::afterCommit(fn () => rescue(fn () => static::dispatch($threadId)));
    }
}
