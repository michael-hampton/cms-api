<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Shared contract for transform capabilities the library can apply to a
 * recognised image host: crop, resize, format change, quality change.
 */
interface ImageTransformCapabilities
{
    public function canCrop(): bool;

    public function canResize(): bool;

    public function canChangeFormat(): bool;

    public function canChangeQuality(): bool;
}
