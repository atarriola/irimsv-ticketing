<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Determine whether the status still needs attention.
     */
    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /**
     * Get the statuses that still need attention.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Open, self::InProgress];
    }
}
