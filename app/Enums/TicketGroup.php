<?php

namespace App\Enums;

enum TicketGroup: string
{
    case All = 'all';
    case Issues = 'issues';
    case FeatureRequests = 'feature_requests';

    /**
     * Get the human-readable label for the group.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Issues => 'Bugs & problems',
            self::FeatureRequests => 'Feature requests',
        };
    }

    /**
     * Get the ticket types that belong to the group.
     *
     * @return list<TicketType>
     */
    public function types(): array
    {
        return match ($this) {
            self::All => TicketType::cases(),
            self::Issues => [TicketType::BugReport, TicketType::Problem],
            self::FeatureRequests => [TicketType::FeatureRequest],
        };
    }

    /**
     * Get the group a ticket type belongs to.
     */
    public static function forType(TicketType $type): self
    {
        return $type === TicketType::FeatureRequest ? self::FeatureRequests : self::Issues;
    }
}
