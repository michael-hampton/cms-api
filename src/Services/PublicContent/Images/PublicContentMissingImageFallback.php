<?php

namespace App\Services\PublicContent\Images;

/**
 * Sensible stand-in when a content image is missing or fails to load.
 */
final class PublicContentMissingImageFallback
{
    public const string PUBLIC_URL = '/public/images/fallback';

    public const string ASSET_RELATIVE_PATH = '/public/images/placeholders/content-image-unavailable.svg';

    public function onerrorHandler(): string
    {
        return "this.onerror=null;this.src='" . self::PUBLIC_URL . "'";
    }
}
