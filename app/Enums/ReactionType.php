<?php

namespace App\Enums;

enum ReactionType: string
{
    case Heart = 'heart';
    case Like = 'like';
    case Laugh = 'laugh';
    case Wow = 'wow';
    case Sad = 'sad';

    /**
     * Get the human-readable label for the reaction.
     */
    public function label(): string
    {
        return match ($this) {
            self::Heart => 'Love',
            self::Like => 'Like',
            self::Laugh => 'Haha',
            self::Wow => 'Wow',
            self::Sad => 'Sad',
        };
    }

    /**
     * Get the emoji shown for the reaction.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Heart => '❤️',
            self::Like => '👍',
            self::Laugh => '😂',
            self::Wow => '😮',
            self::Sad => '😢',
        };
    }

    /**
     * Get every reaction as a value, label and emoji set for the client.
     *
     * @return list<array{value: string, label: string, emoji: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'emoji' => $type->emoji(),
        ], self::cases());
    }
}
