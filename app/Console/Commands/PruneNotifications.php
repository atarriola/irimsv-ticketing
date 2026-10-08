<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

#[Signature('notifications:prune {--days= : Remove read notifications older than this many days, instead of the configured number}')]
#[Description('Remove notifications that were read long ago, so the bell does not keep history forever')]
class PruneNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('helpdesk.prune_notifications_after_days'));

        $removed = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<=', now()->subDays($days))
            ->delete();

        $this->info("Removed {$removed} ".($removed === 1 ? 'notification' : 'notifications')." read {$days} or more days ago.");

        return self::SUCCESS;
    }
}
