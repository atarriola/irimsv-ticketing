<?php

namespace App\Enums;

enum ForumThreadType: string
{
    case Concern = 'concern';
    case Query = 'query';

    /**
     * Get the human-readable label for the thread type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Concern => 'Concern',
            self::Query => 'Query',
        };
    }
}
