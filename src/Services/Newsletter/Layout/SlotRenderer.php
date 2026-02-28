<?php

namespace App\Services\Newsletter\Layout;

use App\DTO\Newsletters\Layout\SlotDTO;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;

/**
 * Renders a single slot's blocks to HTML.
 * Delegates each block to EmailBlockRendererRegistry.
 * No newsletter-specific logic.
 */
class SlotRenderer
{
    public function __construct(
        private readonly EmailBlockRendererRegistry $blockRegistry,
    )
    {
    }

    public function render(SlotDTO $slot, array $context = []): string
    {
        if ($slot->isEmpty()) {
            return '';
        }

        $blocksHtml = collect($slot->blocks)
            ->map(function (array $block) use ($context) {
                $type = $block['type'] ?? null;

                if (!$type || !$this->blockRegistry->has($type)) {
                    return '';
                }

                return $this->blockRegistry->render($type, $block['data'] ?? $block, $context);
            })
            ->filter(fn(string $html) => $html !== '')
            ->implode("\n");

        if (empty(trim($blocksHtml))) {
            return '';
        }

        return sprintf(
            '<div class="layout-slot" data-slot="%s">%s</div>',
            htmlspecialchars($slot->name, ENT_QUOTES),
            $blocksHtml,
        );
    }
}