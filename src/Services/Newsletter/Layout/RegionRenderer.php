<?php

namespace App\Services\Newsletter\Layout;

use App\DTO\Newsletters\Layout\RegionDTO;
use App\DTO\Newsletters\Layout\SlotDTO;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

/**
 * Renders a single region to HTML.
 * Delegates slot rendering to SlotRenderer.
 * Does not know about newsletters — context-agnostic.
 */
class RegionRenderer
{
    public function __construct(
        private readonly SlotRenderer $slotRenderer,
    )
    {
    }

    public function render(RegionDTO $region, ?NewsletterRenderContext $context = null): string
    {
        if ($region->isEmpty()) {
            return '';
        }

        $slotsHtml = $region->getSlots()
            ->map(fn(SlotDTO $slot) => $this->slotRenderer->render($slot, $context))
            ->filter(fn(string $html) => $html !== '')
            ->implode("\n");

        if (empty(trim($slotsHtml))) {
            return '';
        }

        return sprintf(
            '<div class="layout-region layout-region--%s" data-region="%s">%s</div>',
            htmlspecialchars($region->id, ENT_QUOTES),
            htmlspecialchars($region->id, ENT_QUOTES),
            $slotsHtml,
        );
    }
}