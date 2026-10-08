<?php

namespace App\Enums;

enum WaitingOn: string
{
    case Support = 'support';
    case Requester = 'requester';

    /**
     * Get the label shown on a ticket for whose turn it is.
     */
    public function label(): string
    {
        return match ($this) {
            self::Support => 'Waiting on support',
            self::Requester => 'Waiting on requester',
        };
    }
}
