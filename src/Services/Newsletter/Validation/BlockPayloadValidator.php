<?php

namespace App\Services\Newsletter\Validation;

use App\Services\Newsletter\Services\BlockDataFactory;

/**
 * Validates that a raw block payload array can be hydrated by the existing
 * BlockDataFactory. Reuses existing DTO hydration — no new validation logic.
 */
class BlockPayloadValidator
{
    /** Block types registered in the system. Mirrors EmailBlockRendererRegistry. */
    private const KNOWN_TYPES = [
        'heading', 'text', 'image', 'quote', 'cta', 'divider',
        'banner', 'product', 'stats', 'testimonial', 'list', 'table',
        'info', 'person', 'hero', 'section', 'product-comparison',
        'schema', 'award', 'note', 'buying-guide', 'contact-form',
        'deal', 'offer', 'reward',
    ];

    public function __construct(
        private readonly BlockDataFactory $blockDataFactory,
    )
    {
    }

    /**
     * @throws \InvalidArgumentException on unknown type or malformed data
     */
    public function validate(array $blocks): void
    {
        foreach ($blocks as $index => $block) {
            $type = $block['type'] ?? null;

            if (!$type) {
                throw new \InvalidArgumentException(
                    "Block at index {$index} is missing a type."
                );
            }

            if (!in_array($type, self::KNOWN_TYPES, true)) {
                throw new \InvalidArgumentException(
                    "Unknown block type '{$type}' at index {$index}."
                );
            }

            try {
                $data = $block['data'] ?? $block;
                $this->blockDataFactory->create($type, $data);
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException(
                    "Block at index {$index} (type '{$type}') failed validation: {$e->getMessage()}",
                    previous: $e
                );
            }
        }
    }
}