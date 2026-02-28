<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Framework\Support\Str;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterLayoutVersion;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Adverts\PromotionInjector;
use App\Services\Newsletter\Branding\CssSanitizer;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;
use App\Services\Newsletter\Services\TrackingUrlBuilder;

class NewsletterPageBuilderService
{
    /**
     * Placeholder replaced per-recipient by NewsletterDispatcher.
     * Encodes both the snapshot token (which page to serve) and the recipient
     * token (who opened it, for click attribution).
     */
    public const VIEW_IN_BROWSER_PLACEHOLDER = '{{VIEW_IN_BROWSER_URL}}';

    /**
     * @var EmailBlockRenderer[]
     */
    private array $renderers = [];

    public function __construct(
        private readonly PageRepository       $pageRepository,
        private readonly PromotionInjector    $injector,
        private readonly TrackingUrlBuilder   $trackingUrlBuilder,
        private readonly BlockDataFactory     $blockDataFactory,
        private readonly Logger               $logger,
        private readonly NewsletterRepository $newsletterRepository,
        private readonly CssSanitizer $cssSanitizer,
        EmailBlockRendererRegistry            $rendererRegistry,
    )
    {
        $this->renderers = $rendererRegistry->all();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function getPagesForNewsletter(Newsletter $newsletter, int $siteId): Collection
    {
        return $this->newsletterRepository->getPagesForNewsletter($newsletter, $siteId);
    }

    public function buildNewsletterHtml(
        Newsletter $newsletter,
        Collection $pages,
        ?Member    $member = null,
        ?string    $unsubscribeToken = null,
        bool       $includeBlocks = false,
        ?int       $sendId = null,
        ?int                             $siteId = null,
        ?NewsletterBrandingConfiguration $branding = null,
        ?NewsletterLayoutVersion         $layoutVersion = null
    ): string
    {
        if ($layoutVersion !== null) {
            $slots = $layoutVersion->slots();
            $hasSlotBlocks = false;
            foreach ($slots as $slot) {
                if (!empty($slot['blocks'])) {
                    $hasSlotBlocks = true;
                    break;
                }
            }

            if ($hasSlotBlocks) {
                return $this->buildNewsletterHtmlFromLayoutSlots(
                    $newsletter,
                    $layoutVersion,
                    $member,
                    $unsubscribeToken,
                    $siteId,
                    $branding
                );
            }
        }

        $pageHtml = $this->buildPages(
            $newsletter,
            $pages,
            $member,
            $unsubscribeToken,
            $includeBlocks,
            $sendId,
            $siteId,
            $branding
        );

        return $this->buildTemplate($newsletter, $pageHtml, $siteId, $branding, $unsubscribeToken);
    }

    public function buildNewsletterHtmlFromBlocks(
        Newsletter $newsletter,
        Page       $page,
        ?string    $unsubscribeToken = null,
        ?Member    $member = null,
        ?int       $siteId = null
    ): string
    {
        $context = new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: $member,
            sendId: null,
            includeTracking: false
        );

        $html = [];
        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">';
        $html[] = '<div style="background: white; padding: 30px; border-radius: 8px;">';

        $blocks = $this->getBlocks($page);

        foreach ($blocks as $blockArray) {
            $rendered = $this->renderBlock($blockArray, $context);
            if ($rendered->wasRendered) {
                $html[] = $rendered->html;
            }
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    public function renderSlotBlocks(array $slotBlocks, NewsletterRenderContext $context): string
    {
        $html = [];
        foreach ($slotBlocks as $blockArray) {
            $normalised = [
                'type' => $blockArray['type'] ?? 'text',
                'data' => $blockArray['data'] ?? $blockArray,
            ];
            $rendered = $this->renderBlock($normalised, $context);
            if ($rendered->wasRendered) {
                $html[] = $rendered->html;
            }
        }
        return implode("\n", $html);
    }

    public function buildNewsletterHtmlFromLayoutSlots(
        Newsletter                       $newsletter,
        NewsletterLayoutVersion|array $layoutVersion,
        ?Member                          $member = null,
        ?string                          $unsubscribeToken = null,
        ?int                             $siteId = null,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $context = new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: $member,
            sendId: null,
            includeTracking: false
        );

        $slots = $layoutVersion instanceof NewsletterLayoutVersion
            ? $layoutVersion->slots()
            : $layoutVersion['slots'];
        $html = [];

        foreach ($slots as $slot) {
            $slotKey = $slot['key'] ?? null;
            $blocks = $slot['blocks'] ?? [];

            if (!$slotKey || empty($blocks)) {
                continue;
            }

            $html[] = sprintf(
                '<div class="nl-slot nl-slot--%s" data-slot="%s">',
                htmlspecialchars($slotKey),
                htmlspecialchars($slotKey)
            );
            $html[] = $this->renderSlotBlocks($blocks, $context);
            $html[] = '</div>';
        }

        $slotHtml = implode("\n", $html);

        return $this->buildTemplate($newsletter, $slotHtml, $siteId, $branding, $unsubscribeToken);
    }

    // -------------------------------------------------------------------------
    // Template assembly
    // -------------------------------------------------------------------------

    private function buildPages(
        Newsletter $newsletter,
        Collection $pages,
        ?Member    $member = null,
        ?string    $unsubscribeToken = null,
        bool       $includeBlocks = false,
        ?int       $sendId = null,
        ?int                             $siteId = null,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $template = $newsletter->template ?? 'default';

        $context = new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: $member,
            sendId: $sendId,
            includeTracking: $includeBlocks
        );

        return match ($template) {
            'digest' => $this->buildDigestTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks, $branding),
            'featured' => $this->buildFeaturedTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks, $branding),
            'simple' => $this->buildSimpleTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks, $branding),
            default => $this->buildDefaultTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks, $branding),
        };
    }

    /**
     * Wraps rendered page/slot HTML in the outer email chrome.
     *
     * The view-in-browser banner is injected at the very top of the <body>, before
     * the logo header.  The placeholder {{VIEW_IN_BROWSER_URL}} is replaced by
     * NewsletterDispatcher with a per-recipient tracked URL at dispatch time.
     */
    private function buildTemplate(
        Newsletter                       $newsletter,
        string                           $blockHtml,
        ?int                             $siteId = null,
        ?NewsletterBrandingConfiguration $branding = null,
        ?string                          $unsubscribeToken = null
    ): string
    {
        if (!$siteId) {
            return '';
        }

        $site = Site::findOrFail($siteId);

        $logoUrl = $branding?->logo_url ?? $site->getLogoUrl();
        $footerText = $branding?->footer_text
            ?? ('&copy; ' . date('Y') . ' ' . htmlspecialchars($site->name) . '. All rights reserved.');

        $customCssTag = '';
        if ($branding?->custom_css) {
            $sanitized = $this->cssSanitizer->sanitizeAndScope(
                $branding->custom_css,
                $newsletter->id
            );
            if (!empty(trim($sanitized))) {
                $customCssTag = "<style>\n{$sanitized}\n</style>\n";
            }
        }

        $scopeId = 'newsletter-' . $newsletter->id;

        // View-in-browser banner — placeholder is resolved per-recipient at dispatch time.
        $viewInBrowserBanner = $this->buildViewInBrowserBanner();

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . Str::sanitize($newsletter->title) . '</title>
    ' . $customCssTag . '
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
<div id="' . $scopeId . '">
    ' . $viewInBrowserBanner . '
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff;">
                    <!-- Logo Header -->
                    <tr>
                        <td align="center" style="padding: 30px 20px; background-color: #ffffff; border-bottom: 2px solid #e0e0e0;">
                            ' . $this->buildLogoHtml($logoUrl, $site->name) . '
                        </td>
                    </tr>

                    <!-- Newsletter Content -->
                    <tr>
                        <td style="padding: 20px;">
                            ' . $blockHtml . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px; background-color: #f8f8f8; text-align: center; font-size: 12px; color: #666;">
                            ' . $this->renderFooter($unsubscribeToken, $branding) . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>';

        return $html;
    }

    /**
     * Returns the view-in-browser banner HTML.
     *
     * The URL placeholder {{VIEW_IN_BROWSER_URL}} is replaced per-recipient at
     * dispatch time by NewsletterDispatcher, so the link is unique and tracked.
     */
    private function buildViewInBrowserBanner(): string
    {
        $placeholder = self::VIEW_IN_BROWSER_PLACEHOLDER;

        return <<<HTML
<div style="text-align: center; padding: 8px; background-color: #f8f8f8; font-size: 12px; color: #666; border-bottom: 1px solid #e0e0e0;">
    <p style="margin: 0;">
        Having trouble viewing this email?
        <a href="{$placeholder}" style="color: #666; text-decoration: underline;">View it in your browser</a>
    </p>
</div>
HTML;
    }

    private function buildLogoHtml(?string $logoUrl, string $siteName): string
    {
        if ($logoUrl) {
            return sprintf(
                '<img src="%s" alt="%s" style="max-width: 200px; height: auto;">',
                Str::sanitize($logoUrl),
                htmlspecialchars($siteName)
            );
        }

        return sprintf(
            '<div style="font-size: 24px; font-weight: bold; color: #1a202c;">%s</div>',
            htmlspecialchars($siteName)
        );
    }

    // -------------------------------------------------------------------------
    // Template variants (digest / featured / simple / default)
    // These are unchanged from the original except for the footer — which now
    // does NOT render inside these methods; it is rendered once inside
    // buildTemplate() so the unsubscribe token is always available.
    // -------------------------------------------------------------------------

    private function buildDigestTemplate(
        Newsletter                       $newsletter,
        Collection                       $pages,
        NewsletterRenderContext          $context,
        ?string                          $unsubscribeToken,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $html = [];

        $primaryColor = $branding?->theme_json['primary_color'] ?? '#007bff';

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f5f5f5;">';
        $html[] = '<div style="background: #ffffff; padding: 30px 20px; border-bottom: 3px solid ' . htmlspecialchars($primaryColor) . ';">';
        $html[] = '<h1 style="color: #333; margin: 0 0 5px 0; font-size: 24px;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="color: #666; font-size: 13px; margin: 0;">' . date('l, F j, Y') . ' • ' . $pages->count() . ' articles</p>';
        $html[] = '</div>';
        $html[] = '<div style="background: #ffffff; padding: 20px;">';

        $promotionBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            $newsletter->id,
            $context->member,
            $context->siteId,
            'newsletter'
        );

        $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

        foreach ($allContent as $index => $item) {
            if ($index > 0) {
                $html[] = '<div style="border-top: 1px solid #eee; margin: 15px 0;"></div>';
            }

            if (isset($item['is_promotion']) && $item['is_promotion']) {
                $rendered = $this->renderBlock($item, $context);
                if ($rendered->wasRendered) {
                    $html[] = $rendered->html;
                }
            } else {
                $html[] = $this->renderDigestItem($item, $context, $includeBlocks, $branding);
            }
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderDigestItem(
        array                            $page,
        NewsletterRenderContext          $context,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);
        $primaryColor = $branding?->theme_json['primary_color'] ?? '#007bff';

        $html = [];
        $html[] = '<div style="margin-bottom: 20px; display: table; width: 100%;">';

        $listingImageId = $page['listing_image_id'] ?? null;
        $heroImageId = $page['hero_image_id'] ?? null;

        if ($listingImageId || $heroImageId) {
            $imageId = $listingImageId ?: $heroImageId;
            $html[] = '<div style="display: table-cell; width: 100px; padding-right: 15px; vertical-align: top;">';
            $html[] = '<a href="' . $url . '">';
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($title) . '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px; display: block;">';
            $html[] = '</a>';
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';

        if (!empty($page['categories'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $html[] = '<div style="color: ' . htmlspecialchars($primaryColor) . '; font-size: 11px; text-transform: uppercase; margin-bottom: 5px;">' . implode(', ', $categoryNames) . '</div>';
        }

        if (!empty($page['tags'])) {
            $tagNames = array_map(fn($tag) => htmlspecialchars($tag['name']), $page['tags']);
            $html[] = '<div style="color: ' . htmlspecialchars($primaryColor) . '; font-size: 11px; text-transform: uppercase; margin-bottom: 5px;">' . implode(', ', $tagNames) . '</div>';
        }

        $html[] = '<h3 style="margin: 0 0 6px 0; font-size: 16px; line-height: 1.4;">';
        $html[] = '<a href="' . $url . '" style="color: #1a1a1a; text-decoration: none; font-weight: 600;">';
        $html[] = htmlspecialchars($title);
        $html[] = '</a>';
        $html[] = '</h3>';

        $metaInfo = [];
        if (!empty($page['authors'])) {
            $metaInfo[] = 'By ' . htmlspecialchars($page['authors'][0]['name']);
        }
        if (isset($page['published_at'])) {
            $publishedDate = is_string($page['published_at']) ? new \DateTime($page['published_at']) : $page['published_at'];
            $metaInfo[] = $publishedDate->format('M j, Y');
        }
        if (!empty($metaInfo)) {
            $html[] = '<div style="color: #999; font-size: 12px; margin-bottom: 6px;">' . implode(' • ', $metaInfo) . '</div>';
        }

        if ($includeBlocks) {
            $pageBlocks = $page['blocks'] ?? [];
            if (!empty($pageBlocks)) {
                foreach ($pageBlocks as $block) {
                    $rendered = $this->renderBlock($block, $context);
                    if ($rendered->wasRendered) {
                        $html[] = $rendered->html;
                    }
                }
            }
        }

        $metaDescription = $page['meta_description'] ?? null;
        if ($metaDescription && !$includeBlocks) {
            $html[] = '<p style="color: #666; font-size: 13px; margin: 0; line-height: 1.5;">';
            $html[] = htmlspecialchars(substr($metaDescription, 0, 100)) . '...';
            $html[] = '</p>';
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildFeaturedTemplate(
        Newsletter                       $newsletter,
        Collection                       $pages,
        NewsletterRenderContext          $context,
        ?string                          $unsubscribeToken,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $html = [];

        $html[] = '<div style="max-width: 800px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #000000;">';

        $featuredPage = $pages->first();
        if ($featuredPage) {
            $html[] = $this->renderHeroPage($featuredPage, $context);
            $pages = $pages->slice(1);
        }

        if ($pages->count() > 0) {
            $html[] = '<div style="background: #ffffff; padding: 40px 20px;">';
            $html[] = '<h2 style="color: #333; margin: 0 0 30px 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #000; padding-bottom: 10px;">Also in this issue</h2>';

            $promotionBlocks = $this->injector->getBlocksForSurface(
                'newsletter_issue',
                $newsletter->id,
                $context->member,
                $context->siteId,
                'newsletter'
            );

            $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

            foreach ($allContent as $item) {
                if (isset($item['is_promotion']) && $item['is_promotion']) {
                    $rendered = $this->renderBlock($item, $context);
                    if ($rendered->wasRendered) {
                        $html[] = $rendered->html;
                    }
                } else {
                    $html[] = $this->renderCompactCard($item, $context, $includeBlocks, $branding);
                }
            }

            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderHeroPage($page, NewsletterRenderContext $context): string
    {
        $isArray = is_array($page);
        if (!$isArray) {
            $page = $page->toArray();
        }

        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);

        $html = [];
        $html[] = '<div style="position: relative; background: #000000; margin-bottom: 0;">';
        $heroImageId = $page['hero_image_id'] ?? null;
        $listingImageId = $page['listing_image_id'] ?? null;

        if ($heroImageId || $listingImageId) {
            $imageId = $heroImageId ?: $listingImageId;
            $html[] = '<a href="' . $url . '">';
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($title) . '" style="width: 100%; height: 450px; object-fit: cover; display: block; opacity: 0.85;">';
            $html[] = '</a>';
            $html[] = '<div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 50%, transparent 100%); padding: 40px 30px;">';

            $metaItems = [];
            if (!empty($page['categories'])) {
                $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
                $metaItems[] = '<span style="color: rgba(255,255,255,0.9); font-size: 12px; text-transform: uppercase;">' . implode(' • ', $categoryNames) . '</span>';
            }
            if (!empty($page['tags'])) {
                $tagNames = array_map(fn($tag) => htmlspecialchars($tag['name']), $page['tags']);
                $metaItems[] = '<span style="color: rgba(255,255,255,0.9); font-size: 12px; text-transform: uppercase;">' . implode(' • ', $tagNames) . '</span>';
            }
            if (!empty($page['authors'])) {
                $metaItems[] = '<span style="color: rgba(255,255,255,0.8); font-size: 12px;">By ' . htmlspecialchars($page['authors'][0]['name']) . '</span>';
            }
            if (isset($page['published_at'])) {
                $publishedDate = is_string($page['published_at']) ? new \DateTime($page['published_at']) : $page['published_at'];
                $metaItems[] = '<span style="color: rgba(255,255,255,0.8); font-size: 12px;">' . $publishedDate->format('F j, Y') . '</span>';
            }
            if (!empty($metaItems)) {
                $html[] = '<div style="margin-bottom: 15px;">' . implode(' • ', $metaItems) . '</div>';
            }

            $html[] = '<h1 style="margin: 0; font-size: 36px; line-height: 1.2; color: #ffffff; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">';
            $html[] = '<a href="' . $url . '" style="color: #ffffff; text-decoration: none;">' . htmlspecialchars($title) . '</a>';
            $html[] = '</h1>';

            $metaDescription = $page['meta_description'] ?? null;
            if ($metaDescription) {
                $html[] = '<p style="color: rgba(255,255,255,0.95); font-size: 18px; line-height: 1.6; margin: 15px 0 20px 0; max-width: 600px; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">' . htmlspecialchars($metaDescription) . '</p>';
            }

            $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 14px 32px; background-color: #ffffff; color: #000000; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Read Now</a>';
            $html[] = '</div>';
        } else {
            $html[] = '<div style="padding: 60px 30px; background: #000000;">';

            $metaItems = [];
            if (!empty($page['categories'])) {
                $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
                $metaItems[] = implode(' • ', $categoryNames);
            }
            if (!empty($page['authors'])) {
                $metaItems[] = 'By ' . htmlspecialchars($page['authors'][0]['name']);
            }
            if (isset($page['published_at'])) {
                $publishedDate = is_string($page['published_at']) ? new \DateTime($page['published_at']) : $page['published_at'];
                $metaItems[] = $publishedDate->format('F j, Y');
            }
            if (!empty($metaItems)) {
                $html[] = '<div style="color: rgba(255,255,255,0.7); font-size: 14px; text-transform: uppercase; margin-bottom: 15px;">' . implode(' • ', $metaItems) . '</div>';
            }

            $html[] = '<h1 style="margin: 0 0 20px 0; font-size: 42px; color: #ffffff; font-weight: 800;">';
            $html[] = '<a href="' . $url . '" style="color: #ffffff; text-decoration: none;">' . htmlspecialchars($title) . '</a>';
            $html[] = '</h1>';

            $metaDescription = $page['meta_description'] ?? null;
            if ($metaDescription) {
                $html[] = '<p style="color: rgba(255,255,255,0.9); font-size: 20px; line-height: 1.6; margin: 0 0 30px 0; max-width: 600px;">' . htmlspecialchars($metaDescription) . '</p>';
            }

            $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 14px 32px; background-color: #ffffff; color: #000000; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Read Full Story</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderCompactCard(
        array                            $page,
        NewsletterRenderContext          $context,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);
        $primaryColor = $branding?->theme_json['primary_color'] ?? '#007bff';

        $html = [];
        $html[] = '<div style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 2px solid #f0f0f0;">';

        $metaItems = [];
        if (!empty($page['categories'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $metaItems[] = '<span style="color: ' . htmlspecialchars($primaryColor) . '; font-size: 11px; text-transform: uppercase;">' . implode(', ', $categoryNames) . '</span>';
        }
        if (!empty($page['tags'])) {
            $tagNames = array_map(fn($tag) => htmlspecialchars($tag['name']), $page['tags']);
            $metaItems[] = '<span style="color: ' . htmlspecialchars($primaryColor) . '; font-size: 11px; text-transform: uppercase;">' . implode(', ', $tagNames) . '</span>';
        }
        if (!empty($page['authors'])) {
            $metaItems[] = '<span style="color: #999; font-size: 11px;">By ' . htmlspecialchars($page['authors'][0]['name']) . '</span>';
        }
        if (isset($page['published_at'])) {
            $publishedDate = is_string($page['published_at']) ? new \DateTime($page['published_at']) : $page['published_at'];
            $metaItems[] = '<span style="color: #999; font-size: 11px;">' . $publishedDate->format('M j, Y') . '</span>';
        }
        if (!empty($metaItems)) {
            $html[] = '<div style="margin-bottom: 8px;">' . implode(' • ', $metaItems) . '</div>';
        }

        $html[] = '<h3 style="margin: 0 0 10px 0; font-size: 18px; line-height: 1.4;">';
        $html[] = '<a href="' . $url . '" style="color: #1a1a1a; text-decoration: none; font-weight: 700;">' . htmlspecialchars($title) . '</a>';
        $html[] = '</h3>';

        if ($includeBlocks) {
            $pageBlocks = $page['blocks'] ?? [];
            if (!empty($pageBlocks)) {
                foreach ($pageBlocks as $block) {
                    $rendered = $this->renderBlock($block, $context);
                    if ($rendered->wasRendered) {
                        $html[] = $rendered->html;
                    }
                }
            }
        }

        $metaDescription = $page['meta_description'] ?? null;
        if ($metaDescription && !$includeBlocks) {
            $html[] = '<p style="color: #666; font-size: 14px; margin: 0; line-height: 1.6;">';
            $html[] = htmlspecialchars(substr($metaDescription, 0, 120)) . '...';
            $html[] = '</p>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildSimpleTemplate(
        Newsletter                       $newsletter,
        Collection                       $pages,
        NewsletterRenderContext          $context,
        ?string                          $unsubscribeToken,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Georgia, serif; background: #ffffff; padding: 40px 20px;">';
        $html[] = '<div style="text-align: center; border-bottom: 1px solid #000; padding-bottom: 20px; margin-bottom: 30px;">';
        $html[] = '<h1 style="font-size: 32px; font-weight: 400; margin: 0; color: #000;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="font-size: 12px; color: #666; margin: 10px 0 0 0; text-transform: uppercase; letter-spacing: 2px;">' . date('F j, Y') . '</p>';
        $html[] = '</div>';

        $promotionBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            $newsletter->id,
            $context->member,
            $context->siteId,
            'newsletter'
        );

        $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

        $html[] = '<div style="line-height: 1.8;">';
        foreach ($allContent as $item) {
            if (isset($item['is_promotion']) && $item['is_promotion']) {
                $rendered = $this->renderBlock($item, $context);
                if ($rendered->wasRendered) {
                    $html[] = '<div style="margin-bottom: 25px;">' . $rendered->html . '</div>';
                }
            } else {
                $pageId = $item['id'];
                $title = $item['title'];
                $slug = $item['slug'];
                $metaDescription = $item['meta_description'] ?? null;
                $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);

                $html[] = '<div style="margin-bottom: 25px;">';
                $html[] = '<h2 style="font-size: 18px; font-weight: 600; margin: 0 0 8px 0;">';
                $html[] = '<a href="' . $url . '" style="color: #000; text-decoration: none;">' . htmlspecialchars($title) . '</a>';
                $html[] = '</h2>';

                if ($metaDescription) {
                    $html[] = '<p style="color: #666; margin: 0; font-size: 14px; line-height: 1.6;">';
                    $html[] = htmlspecialchars(substr($metaDescription, 0, 120)) . '...';
                    $html[] = '</p>';
                }
                $html[] = '</div>';
            }
        }
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildDefaultTemplate(
        Newsletter                       $newsletter,
        Collection                       $pages,
        NewsletterRenderContext          $context,
        ?string                          $unsubscribeToken,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $html = [];

        $primaryColor = $branding?->theme_json['primary_color'] ?? '#667eea';
        $secondaryColor = $branding?->theme_json['secondary_color'] ?? '#764ba2';
        $textColor = $branding?->theme_json['text_color'] ?? '#ffffff';

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #ffffff;">';
        $html[] = '<div style="background: linear-gradient(135deg, ' . htmlspecialchars($primaryColor) . ' 0%, ' . htmlspecialchars($secondaryColor) . ' 100%); padding: 40px 30px; text-align: center;">';
        $html[] = '<h1 style="color: ' . htmlspecialchars($textColor) . '; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="color: ' . htmlspecialchars($textColor) . '; opacity: 0.9; margin: 10px 0 0 0; font-size: 14px;">' . date('F j, Y') . '</p>';
        $html[] = '</div>';
        $html[] = '<div style="padding: 30px 20px;">';

        $promotionBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            $newsletter->id,
            $context->member,
            $context->siteId,
            'newsletter'
        );

        $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

        foreach ($allContent as $item) {
            if (isset($item['is_promotion']) && $item['is_promotion']) {
                $rendered = $this->renderBlock($item, $context);
                if ($rendered->wasRendered) {
                    $html[] = $rendered->html;
                }
            } else {
                $html[] = $this->renderPageCard($item, $context, $includeBlocks, $branding);
            }
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderPageCard(
        array                            $page,
        NewsletterRenderContext          $context,
        bool                             $includeBlocks,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);
        $primaryColor = $branding?->theme_json['primary_color'] ?? '#667eea';
        $ctaColor = $branding?->theme_json['primary_color'] ?? '#007bff';

        $html = [];
        $html[] = '<div style="background: #ffffff; margin-bottom: 30px; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #f0f0f0;">';

        $listingImageId = $page['listing_image_id'] ?? null;
        $heroImageId = $page['hero_image_id'] ?? null;

        if ($listingImageId || $heroImageId) {
            $imageId = $listingImageId ?: $heroImageId;
            $html[] = '<a href="' . $url . '" style="display: block;">';
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($title) . '" style="width: 100%; height: 250px; object-fit: cover; display: block;">';
            $html[] = '</a>';
        }

        $html[] = '<div style="padding: 25px;">';
        $html[] = '<div style="margin-bottom: 12px;">';
        $metaItems = [];

        if (!empty($page['categories'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $metaItems[] = '<span style="color: ' . htmlspecialchars($primaryColor) . '; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">' . implode(', ', $categoryNames) . '</span>';
        }
        if (isset($page['published_at'])) {
            $publishedDate = is_string($page['published_at']) ? new \DateTime($page['published_at']) : $page['published_at'];
            $metaItems[] = '<span style="color: #999; font-size: 12px;">' . $publishedDate->format('F j, Y') . '</span>';
        }
        if (!empty($page['authors'])) {
            $metaItems[] = '<span style="color: #666; font-size: 12px;">By ' . htmlspecialchars($page['authors'][0]['name']) . '</span>';
        }

        if (!empty($metaItems)) {
            $html[] = implode('<span style="color: #999; font-size: 12px; margin: 0 8px;">•</span>', $metaItems);
        }

        $html[] = '</div>';
        $html[] = '<h2 style="margin: 0 0 12px 0; line-height: 1.3;">';
        $html[] = '<a href="' . $url . '" style="color: #1a1a1a; text-decoration: none; font-size: 22px; font-weight: 700;">' . htmlspecialchars($title) . '</a>';
        $html[] = '</h2>';

        if (!empty($page['tags'])) {
            $html[] = '<div style="margin-bottom: 15px;">';
            foreach (array_slice($page['tags'], 0, 3) as $tag) {
                $html[] = '<span style="display: inline-block; padding: 4px 8px; background-color: #f0f0f0; color: #666; font-size: 11px; border-radius: 3px; margin-right: 5px; margin-bottom: 5px;">' . htmlspecialchars($tag['name']) . '</span>';
            }
            $html[] = '</div>';
        }

        if ($includeBlocks) {
            $pageBlocks = $page['blocks'] ?? [];
            if (!empty($pageBlocks)) {
                foreach ($pageBlocks as $block) {
                    $rendered = $this->renderBlock($block, $context);
                    if ($rendered->wasRendered) {
                        $html[] = $rendered->html;
                    }
                }
            }
        }

        if (!$includeBlocks || empty($page['blocks'])) {
            $metaDescription = $page['meta_description'] ?? null;
            $listingSynopsis = $page['listing_synopsis'] ?? null;

            if ($metaDescription || $listingSynopsis) {
                $description = $listingSynopsis ?: $metaDescription;
                $html[] = '<p style="color: #666; line-height: 1.7; margin: 0 0 20px 0; font-size: 15px;">';
                $html[] = htmlspecialchars(substr($description, 0, 200));
                if (strlen($description) > 200) {
                    $html[] = '...';
                }
                $html[] = '</p>';
            }
        }

        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 10px 20px; background-color: ' . htmlspecialchars($ctaColor) . '; color: white; text-decoration: none; border-radius: 4px;">Read More</a>';
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    // -------------------------------------------------------------------------
    // Footer
    // -------------------------------------------------------------------------

    private function renderFooter(
        ?string                          $unsubscribeToken = null,
        ?NewsletterBrandingConfiguration $branding = null
    ): string
    {
        $html = [];
        $html[] = '<div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999; font-size: 12px;">';

        if ($branding?->footer_text) {
            $html[] = '<p>' . nl2br(htmlspecialchars($branding->footer_text)) . '</p>';
        } else {
            $html[] = '<p>You received this email because you are subscribed to our newsletter.</p>';
        }

        if ($unsubscribeToken) {
            $unsubscribeUrl = url("/member/subscriptions/unsubscribe/{$unsubscribeToken}");
            $manageUrl = url("/member/subscriptions/manage/{$unsubscribeToken}");
            $html[] = '<p><a href="' . $unsubscribeUrl . '" style="color: #999;">Unsubscribe</a> | ';
            $html[] = '<a href="' . $manageUrl . '" style="color: #999;">Manage Preferences</a></p>';
        }

        $html[] = '<p>&copy; ' . date('Y') . ' ' . config('app.name', 'Our Site') . '. All rights reserved.</p>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function renderBlock(array $block, NewsletterRenderContext $context): RenderedBlock
    {
        $type = $block['type'] ?? 'text';
        $data = $block['data'] ?? $block;

        try {
            $blockData = $this->blockDataFactory->create($type, $data);

            foreach ($this->renderers as $renderer) {
                if ($renderer->supports($type)) {
                    return $renderer->render($blockData, $context);
                }
            }

            $this->logger->warning('No renderer found for block type', ['type' => $type]);

            return RenderedBlock::skipped();
        } catch (\Exception $e) {
            $this->logger->error('Failed to render block', ['type' => $type, 'error' => $e->getMessage()]);

            return RenderedBlock::skipped();
        }
    }

    private function getBlocks(Page $page): Collection
    {
        $blocks = $page->blocks;

        if ($blocks->isEmpty()) {
            return collect([]);
        }

        return collect(!is_array($blocks) ? $blocks->toArray() : $blocks);
    }

    private function mergeContentAndPromotions(array $pages, array $promotionBlocks): array
    {
        $toArray = function ($page) {
            return is_array($page) ? $page : [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'meta_description' => $page->meta_description,
                'listing_synopsis' => $page->listing_synopsis,
                'listing_image_id' => $page->listing_image_id,
                'hero_image_id' => $page->hero_image_id,
                'published_at' => $page->published_at,
                'authors' => $page->authors ?? [],
                'blocks' => is_array($page->blocks) ? $page->blocks : $page->blocks->toArray(),
            ];
        };

        if (empty($promotionBlocks)) {
            return array_map($toArray, $pages);
        }

        foreach ($promotionBlocks as &$block) {
            $block['is_promotion'] = true;
        }

        $pagesArray = array_map($toArray, $pages);
        $result = [];
        $promotionIndex = 0;
        $insertFrequency = max(2, (int)ceil(count($pagesArray) / (count($promotionBlocks) + 1)));

        for ($i = 0; $i < count($pagesArray); $i++) {
            $result[] = $pagesArray[$i];

            if (($i + 1) % $insertFrequency === 0 && $promotionIndex < count($promotionBlocks)) {
                $result[] = $promotionBlocks[$promotionIndex];
                $promotionIndex++;
            }
        }

        while ($promotionIndex < count($promotionBlocks)) {
            $result[] = $promotionBlocks[$promotionIndex];
            $promotionIndex++;
        }

        return $result;
    }

    private function buildTrackingUrl(int $pageId, string $slug, ?int $sendId, bool $includeTracking): string
    {
        return $this->trackingUrlBuilder->buildPageTrackingUrl($pageId, $slug, $sendId);
    }
}