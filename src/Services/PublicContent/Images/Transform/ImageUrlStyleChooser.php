<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Decides whether a transform needs the rich ("v2") URL shape or can use the
 * simple ("v1") shape.
 *
 * Rich is required whenever a crop is requested, or the source URL was
 * already in rich form (so re-transforming it never silently drops
 * previously-applied crop/parameters).
 */
final class ImageUrlStyleChooser
{
    public function choose(bool $hasCrop, bool $sourceIsRich): ImageUrlStyle
    {
        return ($hasCrop || $sourceIsRich) ? ImageUrlStyle::Rich : ImageUrlStyle::Simple;
    }
}
