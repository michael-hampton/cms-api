<?php

namespace App\Services\Newsletter\Layout;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\DTO\Newsletters\Layout\RegionDTO;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

/**
 * Single rendering entry point for v2 region-based layouts.
 *
 * Structure = Layout + Region
 * Content   = Slots + Blocks
 * Rendering = This pipeline only
 *
 * Callers must NEVER assemble HTML outside this class.
 * The outer email chrome (header, footer, doctype) is NOT
 * this pipeline's concern — that belongs to NewsletterPageBuilderService
 * which calls renderBody() and wraps it in the template.
 */
class LayoutRenderPipeline
{
    public function __construct(
        private readonly RegionRenderer $regionRenderer,
    )
    {
    }

    /**
     * Render all regions in order to a single HTML string.
     *
     * Returns the body content only — no doctype, no outer chrome.
     * NewsletterPageBuilderService wraps this in buildTemplate().
     */
    public function renderBody(
        LayoutRegionValueObject $layout,
        NewsletterRenderContext $context,
    ): string
    {
        $parts = [];

        foreach ($layout->getOrderedRegions() as $region) {
            /** @var RegionDTO $region */
            $html = $this->regionRenderer->render($region, $context);

            if ($html !== '') {
                $parts[] = $html;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Convert the NewsletterRenderContext into the array-based context
     * format expected by RegionRenderer / SlotRenderer.
     *
     * Keeping this adapter here means RegionRenderer stays a simple
     * value-object renderer with no dependency on the DTO layer.
     */
    private function buildRegionContext(NewsletterRenderContext $context): array
    {
        return [
            'newsletter_id' => $context->newsletter->id,
            'site_id' => $context->siteId,
            'member_id' => $context->member?->id,
            'send_id' => $context->sendId,
            'include_tracking' => $context->includeTracking,
        ];
    }
}