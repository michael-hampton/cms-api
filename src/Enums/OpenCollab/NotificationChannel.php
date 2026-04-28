<?php

namespace App\Enums\OpenCollab;

enum NotificationChannel: string
{
    case Email = 'email';
    case InApp = 'in_app';

    /**
     * Returns all channel values as a plain array of strings,
     * e.g. for use in validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}