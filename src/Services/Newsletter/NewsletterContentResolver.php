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

        // Wrap blocks in the center slot structure expected by the page builder
        $centerSlotVersion = $this->buildImplicitLayoutVersion($blocks, $layoutVersion);

        return $this->pageBuilderService->buildNewsletterHtmlFromLayoutSlots(
            $newsletter,
            $centerSlotVersion,
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

        // Render legacy content as a single text block — backwards compatible
        $legacyBlock = [
            'type' => 'text',
            'data' => ['paragraphs' => [nl2br(htmlspecialchars($text))]],
        ];

        $centerSlotVersion = $this->buildImplicitLayoutVersion([$legacyBlock], $layoutVersion);

        return $this->pageBuilderService->buildNewsletterHtmlFromLayoutSlots(
            $newsletter,
            $centerSlotVersion,
            $member,
            $unsubscribeToken,
            $siteId,
            $branding,
        );
    }
}