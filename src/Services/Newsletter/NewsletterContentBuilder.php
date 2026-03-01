<?php

namespace App\Services\Newsletter;

use App\Models\Member;
use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterLayoutRepository;

class NewsletterContentBuilder
{
    private const UNSUBSCRIBE_PLACEHOLDER = '{{UNSUBSCRIBE_LINK}}';

    public function __construct(
        private readonly NewsletterPageBuilderService $pageBuilderService,
        private readonly NewsletterBrandingRepository $newsletterBrandingRepository,
        private readonly NewsletterLayoutRepository $newsletterLayoutRepository,
        private readonly NewsletterContentResolver  $contentResolver,
    )
    {
    }

    public function build(Newsletter $newsletter, int $siteId, bool $isPreview, ?Member $member = null, bool $forceV2 = false): array
    {
        $branding = $this->newsletterBrandingRepository->findByNewsletterId($newsletter->id);
        $layoutVersion = $newsletter->layout_id
            ? $this->newsletterLayoutRepository->versionHistory($newsletter->layout_id)?->last()
            : null;

        try {
            $result = $this->contentResolver->resolve(
                newsletter: $newsletter,
                siteId: $siteId,
                member: $member,
                unsubscribeToken: null,
                isPreview: $isPreview,
                sendId: null,
                branding: $branding,
                layoutVersion: $layoutVersion,
                forceV2: $forceV2,
            );
        } catch (\DomainException $e) {
            // Auto-pages newsletters with no matching pages — preserve existing error contract
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => $e->getMessage(),
            ];
        }

        $html = $result->html;

        // Ensure unsubscribe placeholder exists
        if (!str_contains($html, self::UNSUBSCRIBE_PLACEHOLDER)) {
            $html .= "\n" . self::UNSUBSCRIBE_PLACEHOLDER;
        }

        // Use pages already resolved by the resolver — no second DB call.
        $pages = $this->buildPagesResponse($newsletter, $result->pages);

        return [
            'success' => true,
            'html' => $html,
            'pages' => $pages,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * When the resolver already fetched pages (AutoPages content type), use them
     * directly. For all other content types pages is null — there are no pages to
     * surface, so return an empty array rather than making a redundant DB call.
     */
    private function buildPagesResponse(Newsletter $newsletter, ?array $resolvedPages): array
    {
        if ($resolvedPages === null) {
            return [];
        }

        if (empty($resolvedPages)) {
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'No pages match newsletter criteria',
            ];
        }

        return $resolvedPages;
    }
}