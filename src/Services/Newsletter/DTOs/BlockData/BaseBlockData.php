<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

use App\Services\Newsletter\DTOs\BlockStyle;

/**
 * All concrete block DTOs extend this class.
 *
 * The `style` property carries optional visual overrides (font size, colour,
 * padding) set by the block builder's WYSIWYG toolbar.  Renderers read it via
 * $blockData->style and apply it to their outermost wrapper element.
 */
abstract class BaseBlockData
{
    public readonly BlockStyle $style;

    /**
     * Subclasses call this after their own property assignments to populate
     * the shared style property from the raw `style` key in block data.
     */
    protected function resolveStyle(array $data): void
    {
        $this->style = BlockStyle::fromArray($data['style'] ?? null);
    }

    abstract public static function fromArray(array $data): self;
}