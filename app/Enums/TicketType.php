<?php

namespace App\Enums;

enum TicketType: string
{
    case BugReport = 'bug_report';
    case Problem = 'problem';
    case FeatureRequest = 'feature_request';

    /**
     * Get the human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::BugReport => 'Bug report',
            self::Problem => 'Problem',
            self::FeatureRequest => 'Feature request',
        };
    }
}
