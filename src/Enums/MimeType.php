<?php

namespace App\Enums;

enum MimeType: string
{
    case JPEG = 'image/jpeg';
    case JPG = 'image/jpg';
    case PNG = 'image/png';
    case GIF = 'image/gif';
    case WEBP = 'image/webp';
    case SVG = 'image/svg+xml';

    public static function allowed(): array
    {
        return [
            self::JPEG->value,
            self::JPG->value,
            self::PNG->value,
            self::GIF->value,
            self::WEBP->value,
            self::SVG->value,
        ];
    }

    public function isRasterImage(): bool
    {
        return $this !== self::SVG;
    }
}