<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Shipped = 'shipped';
    case Closed = 'closed';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under review',
            self::Planned => 'Planned',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Shipped => 'Shipped',
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
     * Determine whether the status means the work is done and the requester may confirm or reopen.
     */
    public function isResolution(): bool
    {
        return $this === self::Resolved || $this === self::Shipped;
    }

    /**
     * Get the statuses that still need attention.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Open, self::UnderReview, self::Planned, self::InProgress];
    }

    /**
     * Get the statuses a ticket of the group moves through, in board order.
     *
     * Bugs and problems are fixed; feature requests are reviewed, planned and shipped.
     *
     * @return list<self>
     */
    public static function forGroup(TicketGroup $group): array
    {
        return match ($group) {
            TicketGroup::Issues => [self::Open, self::InProgress, self::Resolved, self::Closed],
            TicketGroup::FeatureRequests => [self::Open, self::UnderReview, self::Planned, self::InProgress, self::Shipped, self::Closed],
            TicketGroup::All => self::cases(),
        };
    }

    /**
     * Get the statuses a ticket of the type moves through.
     *
     * @return list<self>
     */
    public static function forType(TicketType $type): array
    {
        return self::forGroup(TicketGroup::forType($type));
    }
}
