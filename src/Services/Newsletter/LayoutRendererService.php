<?php

namespace App\Services\Newsletter;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\Models\Newsletter;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;
use App\Repositories\Newsletters\NewsletterLayoutRepository;

/**
 * Resolves which layout version to use for a newsletter and validates
 * that the content structure maps to the layout's slot definitions.
 *
 * Layout defines structure only — content is never touched here.
 */
class LayoutRendererService
{
    public function __construct(
        private readonly NewsletterLayoutRepository $layoutRepository,
    )
    {
    }

    /**
     * Resolve the published layout version for a given newsletter.
     * Returns null if no layout is assigned (falls back to legacy template rendering).
     */
    public function resolveLayoutVersion(Newsletter $newsletter): ?NewsletterLayoutVersion
    {
        if (!$newsletter->layout_id) {
            return null;
        }

        $layout = NewsletterLayout::find($newsletter->layout_id);

        if (!$layout) {
            return null;
        }

        return $layout->latestPublishedVersion();
    }

    /**
     * Validate that the provided slot content keys satisfy the layout's
     * required slot definitions. Throws on invalid mapping.
     *
     * @throws \InvalidArgumentException
     */
    public function validateSlotMapping(NewsletterLayoutVersion $version, array $contentSlots): void
    {
        $layoutSlots = $version->slots();

        foreach ($layoutSlots as $slot) {
            if (!isset($slot['key']) || !isset($slot['required'])) {
                continue;
            }

            if ($slot['required'] && !array_key_exists($slot['key'], $contentSlots)) {
                throw new \InvalidArgumentException(
                    "Required layout slot '{$slot['key']}' is missing from newsletter content."
                );
            }

            if (isset($slot['allowed_block_types']) && isset($contentSlots[$slot['key']])) {
                $this->validateBlockTypes(
                    $slot['key'],
                    $slot['allowed_block_types'],
                    $contentSlots[$slot['key']]
                );
            }
        }
    }

    /**
     * Build a migration report when slots differ between layout versions.
     *
     * @return array{mapped: array, unmapped: array, deprecated: array}
     */
    public function buildSlotMigrationReport(
        NewsletterLayoutVersion $oldVersion,
        NewsletterLayoutVersion $newVersion
    ): array
    {
        $oldSlotKeys = array_column($oldVersion->slots(), 'key');
        $newSlotKeys = array_column($newVersion->slots(), 'key');

        $mapped = array_intersect($oldSlotKeys, $newSlotKeys);
        $deprecated = array_diff($oldSlotKeys, $newSlotKeys);
        $added = array_diff($newSlotKeys, $oldSlotKeys);

        return [
            'mapped' => array_values($mapped),
            'unmapped' => array_values($added),
            'deprecated' => array_values($deprecated),
        ];
    }

    /**
     * Check whether a layout version can be used for new newsletters.
     */
    public function isVersionUsableForNewNewsletters(NewsletterLayoutVersion $version): bool
    {
        return $version->state()->canBeUsedForNewNewsletters();
    }

    private function validateBlockTypes(string $slotKey, array $allowed, array $slotContent): void
    {
        foreach ($slotContent as $block) {
            $type = $block['type'] ?? 'unknown';

            if (!in_array($type, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "Block type '{$type}' is not allowed in slot '{$slotKey}'. "
                    . "Allowed types: " . implode(', ', $allowed)
                );
            }
        }
    }

    public function resolveRegions(NewsletterLayoutVersion $version): ?LayoutRegionValueObject
    {
        $definition = $version->definition ?? [];

        if (($definition['schema_version'] ?? 1) < 2) {
            return null;
        }

        return LayoutRegionValueObject::fromArray($definition);
    }
}