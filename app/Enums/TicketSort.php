<?php

namespace App\Enums;

enum TicketSort: string
{
    case Newest = 'newest';
    case Oldest = 'oldest';
    case Active = 'active';
    case Priority = 'priority';
    case Votes = 'votes';

    /**
     * Get the label shown in the sort menu.
     */
    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest first',
            self::Oldest => 'Oldest first',
            self::Active => 'Recently active',
            self::Priority => 'Most urgent',
            self::Votes => 'Most supported',
        };
    }
}
