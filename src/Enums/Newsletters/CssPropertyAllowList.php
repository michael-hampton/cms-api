<?php

namespace App\Enums\Newsletters;

enum CssPropertyAllowList: string
{
    case Color = 'color';
    case Padding = 'padding';
    case Margin = 'margin';
    case FontSize = 'font-size';
    case BackgroundColor = 'background-color';
    case FontFamily = 'font-family';
    case FontWeight = 'font-weight';
    case TextAlign = 'text-align';
    case BorderColor = 'border-color';
    case BorderRadius = 'border-radius';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isAllowed(string $property): bool
    {
        return in_array(strtolower(trim($property)), self::values(), true);
    }
}