<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Deactivated = 'deactivated';

    /**
     * Get the status as it is shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending approval',
            self::Deactivated => 'Deactivated',
        };
    }
}
