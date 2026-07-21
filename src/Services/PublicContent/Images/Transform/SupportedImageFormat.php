<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * File types the transform library is willing to rewrite. Anything else is
 * left completely untouched by {@see RecognisedImageHostTransformer}.
 */
enum SupportedImageFormat: string
{
    case Jpg = 'jpg';
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Gif = 'gif';
    case Webp = 'webp';

    /**
     * Regex alternation fragment (no delimiters/anchors) for building
     * extension-matching patterns, e.g. "jpg|jpeg|png|gif|webp".
     */
    public static function pattern(): string
    {
        return implode('|', array_map(static fn (self $format): string => $format->value, self::cases()));
    }
}
