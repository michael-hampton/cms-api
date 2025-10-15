<?php

namespace App\Enums;

enum InfoType: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case TIP = 'tip';
    case NOTE = 'note';
    case INGREDIENTS = 'ingredients';
    case RECIPE = 'recipe';
    case INSTRUCTIONS = 'instructions';
    case UPDATE = 'update';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public function getIcon(): string
    {
        return match($this) {
            self::INFO => 'ℹ️',
            self::WARNING => '⚠️',
            self::TIP => '💡',
            self::NOTE => '📝',
            self::INGREDIENTS => '🥗',
            self::RECIPE => '👨‍🍳',
            self::INSTRUCTIONS => '📋',
        };
    }
}