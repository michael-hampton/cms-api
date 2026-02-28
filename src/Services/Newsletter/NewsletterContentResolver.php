<?php

namespace App\Services\Newsletter;

use App\Enums\Newsletters\ContentSourceType;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterLayoutVersion;

/**
 * Decides how a newsletter's content should be rendered.
 * Single responsibility: content source resolution and dispatch.
 * Does not build HTML — delegates to NewsletterPageBuilderService.
 */
class NewsletterContentResolver
{
    public function __construct(
        private readonly NewsletterPageBuilderService $pageBuilderService,
        private readonly Logger                       $logger,
    )
    {
    }

    /**
     * Resolve and render newsletter content to HTML.
     * Handles all three content source types with backwards compatibility.
     */
    public function resolve(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member = null,
        ?string                          $unsubscribeToken = null,
        bool                             $isPreview = false,
        ?int                             $sendId = null,
        ?NewsletterBrandingConfiguration $branding = null,
        ?NewsletterLayoutVersion         $layoutVersion = null,
    ): string
    {
        $contentType = ContentSourceType::tryFrom($newsletter->content_type ?? '')
            ?? ContentSourceType::Manual;

        return match ($contentType) {
            ContentSourceType::CustomBlocks => $this->resolveCustomBlocks(
                $newsletter, $siteId, $member, $unsubscribeToken, $branding, $layoutVersion
            ),
            ContentSourceType::AutoPages => $this->resolveAutoPages(
                $newsletter, $siteId, $member, $unsubscribeToken, $isPreview, $sendId, $branding, $layoutVersion
            ),
            ContentSourceType::Manual => $this->resolveLegacy(
                $newsletter, $siteId, $member, $unsubscribeToken, $branding, $layoutVersion
            ),
        };
    }

    private function resolveCustomBlocks(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        ?NewsletterBrandingConfiguration $branding,
        ?NewsletterLayoutVersion         $layoutVersion,
    ): string
    {
        $blocks = $newsletter->getBlocks();

        if (empty($blocks)) {
            $this->logger->warning('Custom blocks newsletter has no blocks', [
                'newsletter_id' => $newsletter->id,
            ]);
            return '';
        }

        $slotPayload = $this->buildCenterSlotPayload($blocks, $layoutVersion);

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
     * Wraps blocks in a NewsletterLayoutVersion-compatible object so the
     * existing rendering pipeline can consume them without modification.
     *
     * If a real layout version is provided and has a center region, blocks
     * are injected there. Otherwise a single implicit slot is used.
     */
    private function buildImplicitLayoutVersion(
        array                    $blocks,
        ?NewsletterLayoutVersion $layoutVersion,
    ): array
    {
        $slots = [
            [
                'key' => 'content',
                'label' => 'Content',
                'required' => true,
                'blocks' => $blocks,
            ]
        ];

        // Anonymously extend — avoids coupling to Eloquent model instantiation
        return [
            'slots' => $slots
        ];
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
    ): string
    {
        $pages = $this->pageBuilderService->getPagesForNewsletter($newsletter, $siteId);

        // Empty collection is valid — page builder returns empty HTML
        // Empty-pages guard lives in NewsletterContentBuilder, not here
        return $this->pageBuilderService->buildNewsletterHtml(
            $newsletter,
            $pages,
            $member,
            $unsubscribeToken,
            $isPreview,
            $sendId,
            $siteId,
            $branding,
            $layoutVersion,
        );
    }

    private function resolveLegacy(
        Newsletter                       $newsletter,
        int                              $siteId,
        ?Member                          $member,
        ?string                          $unsubscribeToken,
        ?NewsletterBrandingConfiguration $branding,
        ?NewsletterLayoutVersion         $layoutVersion,
    ): string
    {
        $text = $newsletter->legacy_content ?? $newsletter->content ?? '';

        if (empty(trim($text))) {
            return '';
        }

        $legacyBlock = [
            'type' => 'text',
            'data' => ['paragraphs' => [nl2br(htmlspecialchars($text))]],
        ];

        $slotPayload = $this->buildCenterSlotPayload([$legacyBlock], $layoutVersion);

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
     * Build a slot payload for buildNewsletterHtmlFromLayoutSlots.
     *
     * For v2 region layouts: returns all region slots in order, with the center
     * region's single implicit content slot populated with newsletter blocks.
     * Non-center region slots pass through unchanged (their blocks come from
     * the layout definition — e.g. a top banner slot).
     *
     * For v1 / no layout: returns a single implicit content slot.
     */
    private function buildCenterSlotPayload(
        array                    $blocks,
        ?NewsletterLayoutVersion $layoutVersion,
    ): array
    {
        // No layout assigned — single implicit slot, existing behaviour
        if ($layoutVersion === null) {
            return [
                'slots' => [
                    ['key' => 'content', 'label' => 'Content', 'required' => true, 'blocks' => $blocks],
                ],
            ];
        }

        $definition = $layoutVersion->definition ?? [];
        $schemaVersion = (int)($definition['schema_version'] ?? 1);

        // v1 layout — existing slot-based behaviour
        if ($schemaVersion < 2) {
            return [
                'slots' => [
                    ['key' => 'content', 'label' => 'Content', 'required' => true, 'blocks' => $blocks],
                ],
            ];
        }

        // v2 region layout — flatten regions in order, inject content into center
        $regions = collect($definition['regions'] ?? [])
            ->sortBy('order')
            ->values();

        $slots = [];

        foreach ($regions as $region) {
            $regionId = $region['id'];

            if ($regionId === 'center') {
                // Newsletter content always lives in a single implicit center slot
                $slots[] = [
                    'key' => 'center_content',
                    'label' => 'Center Content',
                    'required' => true,
                    'blocks' => $blocks,
                ];
            } else {
                // Pass through any slots the designer added to top/bottom regions
                foreach ($region['slots'] ?? [] as $slot) {
                    $slots[] = [
                        'key' => $slot['name'] ?? ($regionId . '_slot'),
                        'label' => $slot['name'] ?? $regionId,
                        'required' => false,
                        'blocks' => $slot['blocks'] ?? [],
                    ];
                }
            }
        }

        return ['slots' => $slots];
    }

}