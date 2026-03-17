<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

/**
 * The constructor param is `$lineStyle`, not `$style`, to avoid shadowing
 * the `$style: BlockStyle` property inherited from BaseBlockData.
 * Legacy block data that stored the line token under 'style' is supported
 * via the fallback in fromArray().
 */
class DividerBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $lineStyle = 'solid',
    )
    {
    }

    public static function fromArray(array $data): static
    {
        // Legacy support: old data stored the line style under 'style'.
        $lineStyle = $data['lineStyle'] ?? $data['style'] ?? 'solid';
        $data['lineStyle'] = $data['style'] ?? 'solid';
        unset($data['style']);

        $instance = new static(lineStyle: $lineStyle);

        $instance->resolveStyle($data);

        return $instance;
    }
}