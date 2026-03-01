<?php

namespace App\Services\Newsletter;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\DTO\Newsletters\NewsletterResolveResult;
use App\Enums\Newsletters\ContentSourceType;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterLayoutVersion;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Layout\LayoutRenderPipeline;

/**
 * Decides how a newsletter's content should be rendered.
 * Single responsibility: content source resolution and dispatch.
 *
 * Rendering contract:
 *   v2 region layout  → LayoutRenderPipeline (RegionRenderer → SlotRenderer → BlockRendererRegistry)
 *                       wrapped in the outer email chrome via buildTemplate()
 *   v1 / no layout    → NewsletterPageBuilderService existing paths (unchanged)
 *
 * This class never assembles HTML directly.
 *
 * Returns a NewsletterResolveResult carrying both the rendered HTML and any
 * pages already fetched internally, so callers never duplicate the DB query.
 */
class NewsletterContentResolver
{
    public function __construct(
        private readonly NewsletterPageBuilderService $pageBuilderService,
        private readonly LayoutRenderPipeline $renderPipeline,
        private readonly Logger                       $logger,
    )
    {
    }

    public function resolve(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member = null,
        ?string                          $unsubscribeToken = null,
        bool                             $isPreview = false,
        ?int                             $sendId = null,
        ?NewsletterBrandingConfiguration $branding = null,
        ?NewsletterLayoutVersion         $layoutVersion = null,
        bool $forceV2 = false
    ): NewsletterResolveResult
    {
        $contentType = ContentSourceType::tryFrom($newsletter->content_type ?? '')
            ?? ContentSourceType::Manual;

        return match ($contentType) {
            ContentSourceType::CustomBlocks => $this->resolveCustomBlocks(
                $newsletter, $siteId, $member, $unsubscribeToken, $branding, $layoutVersion, $sendId, $forceV2
            ),
            ContentSourceType::AutoPages => $this->resolveAutoPages(
                $newsletter, $siteId, $member, $unsubscribeToken, $isPreview, $sendId, $branding, $layoutVersion, $forceV2
            ),
            ContentSourceType::Manual => $this->resolveLegacy(
                $newsletter, $siteId, $member, $unsubscribeToken, $branding, $layoutVersion,
            ),
        };
    }

    // -------------------------------------------------------------------------
    // Content source handlers
    // -------------------------------------------------------------------------

    private function resolveCustomBlocks(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        ?NewsletterBrandingConfiguration $branding,
        ?NewsletterLayoutVersion         $layoutVersion,
        ?int $sendId = null,
        bool $forceV2 = false
    ): NewsletterResolveResult
    {
        $blocks = $newsletter->getBlocks();

        if (empty($blocks)) {
            Logger::warning('Custom blocks newsletter has no blocks', [
                'newsletter_id' => $newsletter->id,
            ]);
            return NewsletterResolveResult::withoutPages('');
        }

        $html = $this->renderWithBlocks(
            $blocks, $newsletter, $siteId, $member, $unsubscribeToken, $sendId, $branding, $layoutVersion, $forceV2
        );

        return NewsletterResolveResult::withoutPages($html);
    }

    private function resolveAutoPages(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        bool                             $isPreview,
        ?int                             $sendId,
        ?NewsletterBrandingConfiguration $branding,
        ?NewsletterLayoutVersion         $layoutVersion,
        bool $forceV2 = false
    ): NewsletterResolveResult
    {
        $pagesCollection = $this->pageBuilderService->getPagesForNewsletter($newsletter, $siteId);

        $mappedPages = $pagesCollection->map(fn($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'subtitle' => $p->subtitle,
            'slug' => $p->slug,
        ])->toArray();

        // v2 layout — convert pages to blocks and render through the region pipeline.
        if ($layoutVersion !== null && ($this->isV2Layout($layoutVersion) || $forceV2)) {
            $blocks = $this->pageBuilderService->convertPagesToBlocks(
                $pagesCollection, $newsletter, $siteId, $member, $sendId,
            );

            $html = $this->renderWithBlocks(
                $blocks, $newsletter, $siteId, $member, $unsubscribeToken, $sendId, $branding, $layoutVersion, $forceV2
            );

            return NewsletterResolveResult::withPages($html, $mappedPages);
        }

        // v1 / no layout — existing page template path, unchanged.
        $html = $this->pageBuilderService->buildNewsletterHtml(
            $newsletter,
            $pagesCollection,
            $member,
            $unsubscribeToken,
            $isPreview,
            $sendId,
            $siteId,
            $branding,
            $layoutVersion,
        );

        return NewsletterResolveResult::withPages($html, $mappedPages);
    }

    private function resolveLegacy(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        ?NewsletterBrandingConfiguration $branding,
        ?NewsletterLayoutVersion         $layoutVersion,
    ): NewsletterResolveResult
    {
        $text = $newsletter->legacy_content ?? $newsletter->content ?? '';

        if (empty(trim($text))) {
            return NewsletterResolveResult::withoutPages('');
        }

        $legacyBlock = [
            'type' => 'text',
            'data' => ['paragraphs' => [nl2br(htmlspecialchars($text))]],
        ];

        $html = $this->renderWithBlocks(
            [$legacyBlock], $newsletter, $siteId, $member, $unsubscribeToken, null, $branding, $layoutVersion,
        );

        return NewsletterResolveResult::withoutPages($html);
    }

    // -------------------------------------------------------------------------
    // Core dispatch
    // -------------------------------------------------------------------------

    /**
     * Route to the correct rendering path based on layout version.
     *
     * v2 layout → LayoutRenderPipeline + buildTemplate() for outer chrome
     * v1 / none → buildNewsletterHtmlFromLayoutSlots() (implicit single-slot path)
     */
    private function renderWithBlocks(
        array                            $blocks,
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        ?int                             $sendId,
        ?NewsletterBrandingConfiguration $branding,
        ?NewsletterLayoutVersion         $layoutVersion,
        bool                             $forceV2 = false
    ): string
    {
        if ($layoutVersion !== null && ($this->isV2Layout($layoutVersion) || $forceV2)) {
            return $this->renderViaRegionPipeline(
                $blocks, $newsletter, $siteId, $member, $unsubscribeToken, $sendId, $branding, $layoutVersion,
            );
        }

        // v1 / no layout — implicit single content slot, existing path.
        $slotPayload = [
            'slots' => [
                ['key' => 'content', 'label' => 'Content', 'required' => true, 'blocks' => $blocks],
            ],
        ];

        return $this->pageBuilderService->buildNewsletterHtmlFromLayoutSlots(
            $newsletter,
            $slotPayload,
            $member,
            $unsubscribeToken,
            $siteId,
            $branding,
        );
    }

    /**
     * Inject blocks into the center region, render all regions through the
     * pipeline, then wrap in the outer email chrome.
     *
     * Non-center regions (top, bottom) render with whatever slots and blocks
     * the designer configured — they pass through LayoutRegionValueObject
     * unchanged.
     *
     * Tracking context is hydrated here so region/slot renderers receive a
     * fully-populated context without depending on the layout layer.
     */
    private function renderViaRegionPipeline(
        array                            $blocks,
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        ?int                             $sendId,
        ?NewsletterBrandingConfiguration $branding,
        NewsletterLayoutVersion          $layoutVersion,
    ): string
    {
        $layout = LayoutRegionValueObject::fromArray($layoutVersion->layout_definition_json ?? []);

        // Inject newsletter content into the center region as a single implicit slot.
        $layout = $layout->withCenterSlots([
            ['name' => 'center_content', 'blocks' => $blocks],
        ]);

        $context = new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: $member,
            sendId: $sendId,
            includeTracking: $sendId !== null,
        );

        $bodyHtml = $this->renderPipeline->renderBody($layout, $context);

        // Outer email chrome (doctype, logo header, footer) is the page builder's
        // responsibility — not the region pipeline's.
        return $this->pageBuilderService->buildTemplate(
            $newsletter,
            $bodyHtml,
            $siteId,
            $branding,
            $unsubscribeToken,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isV2Layout(NewsletterLayoutVersion $version): bool
    {
        return ((int)($version->layout_definition_json['schema_version'] ?? 1)) >= 2;
    }
}