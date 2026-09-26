<?php

namespace App\Enums;

enum NewsKind: string
{
    case Event = 'event';
    case Release = 'release';
    case Update = 'update';
    case Maintenance = 'maintenance';
    case Plan = 'plan';
    case Announcement = 'announcement';

    /**
     * Get the human-readable label for the kind of news.
     */
    public function label(): string
    {
        return match ($this) {
            self::Event => 'Event',
            self::Release => 'New feature',
            self::Update => 'Update',
            self::Maintenance => 'Maintenance',
            self::Plan => 'Future plan',
            self::Announcement => 'Announcement',
        };
    }

    /**
     * Get every kind as value and label pairs for the client.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()], self::cases());
    }
}
