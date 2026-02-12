<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Framework\Support\Str;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Adverts\PromotionInjector;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;
use App\Services\Newsletter\Services\TrackingUrlBuilder;

class NewsletterPageBuilderService
{
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
        EmailBlockRendererRegistry            $rendererRegistry,
    )
    {
        $this->renderers = $rendererRegistry->all();
    }

    /**
     * Get pages for newsletter based on filters
     */
    public function getPagesForNewsletter(Newsletter $newsletter, int $siteId): Collection
    {
        return $this->newsletterRepository->getPagesForNewsletter($newsletter, $siteId);
    }

    /**
     * Build newsletter HTML from pages with tracking
     */
    public function buildNewsletterHtml(
        Newsletter $newsletter,
        Collection $pages,
        ?Member    $member = null,
        ?string    $unsubscribeToken = null,
        bool       $includeBlocks = false,
        ?int       $sendId = null,
        ?int $siteId = null
    ): string
    {
        $pageHtml = $this->buildPages($newsletter, $pages, $member, $unsubscribeToken, $includeBlocks, $sendId, $siteId);

        return $this->buildTemplate($newsletter, $pageHtml, $siteId);
    }

    private function buildPages(
        Newsletter $newsletter,
        Collection $pages,
        ?Member    $member = null,
        ?string    $unsubscribeToken = null,
        bool       $includeBlocks = false,
        ?int       $sendId = null,
        ?int       $siteId = null
    )
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
            'digest' => $this->buildDigestTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks),
            'featured' => $this->buildFeaturedTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks),
            'simple' => $this->buildSimpleTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks),
            default => $this->buildDefaultTemplate($newsletter, $pages, $context, $unsubscribeToken, $includeBlocks),
        };
    }

    private function buildTemplate(Newsletter $newsletter, string $blockHtml, ?int $siteId = null)
    {
        if (!$siteId) {
            return '';
        }

        $site = Site::findOrFail($siteId);

        $logoUrl = $site->getLogoUrl();

        $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . Str::sanitize($newsletter->title) . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff;">
                        <!-- Logo Header -->
                        <tr>
                            <td align="center" style="padding: 30px 20px; background-color: #ffffff; border-bottom: 2px solid #e0e0e0;">
                                <img src="' . Str::sanitize($logoUrl) . '" alt="' . Str::sanitize($site->name) . '" style="max-width: 200px; height: auto;">
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
                                <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($site->name) . '. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

        return $html;
    }

    /**
     * Build newsletter HTML from page blocks
     */
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

    private function renderBlock(array $block, NewsletterRenderContext $context): RenderedBlock
    {
        $type = $block['type'] ?? 'text';
        $data = $block['data'] ?? $block;

        try {
            // Convert raw array to typed DTO
            $blockData = $this->blockDataFactory->create($type, $data);

            // Find renderer
            foreach ($this->renderers as $renderer) {
                if ($renderer->supports($type)) {
                    return $renderer->render($blockData, $context);
                }
            }

            $this->logger->warning('No renderer found for block type', [
                'type' => $type
            ]);

            return RenderedBlock::skipped();

        } catch (\Exception $e) {
            $this->logger->error('Failed to render block', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);

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

    private function buildDigestTemplate(
        Newsletter              $newsletter,
        Collection              $pages,
        NewsletterRenderContext $context,
        ?string                 $unsubscribeToken,
        bool                    $includeBlocks
    ): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f5f5f5;">';

        // Compact header
        $html[] = '<div style="background: #ffffff; padding: 30px 20px; border-bottom: 3px solid #007bff;">';
        $html[] = '<h1 style="color: #333; margin: 0 0 5px 0; font-size: 24px;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="color: #666; font-size: 13px; margin: 0;">' . date('l, F j, Y') . ' • ' . $pages->count() . ' articles</p>';
        $html[] = '</div>';

        $html[] = '<div style="background: #ffffff; padding: 20px;">';

        // GET PROMOTIONS ONCE at the newsletter level
        $promotionBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            $newsletter->id,
            $context->member,
            $context->siteId,
            'newsletter'
        );

        // Merge pages and promotions
        $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

        foreach ($allContent as $index => $item) {
            if ($index > 0) {
                $html[] = '<div style="border-top: 1px solid #eee; margin: 15px 0;"></div>';
            }

            if (isset($item['is_promotion']) && $item['is_promotion']) {
                // Render promotion block
                $rendered = $this->renderBlock($item, $context);
                if ($rendered->wasRendered) {
                    $html[] = $rendered->html;
                }
            } else {
                // Render page item WITH blocks if requested
                $html[] = $this->renderDigestItem($item, $context, $includeBlocks);
            }
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderDigestItem(array $page, NewsletterRenderContext $context, bool $includeBlocks): string
    {
        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);

        $html = [];

        // Compact, scannable format
        $html[] = '<div style="margin-bottom: 20px; display: table; width: 100%;">';

        // Small thumbnail on left
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

        // Categories
        if (!empty($page['categories'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $html[] = '<div style="color: #007bff; font-size: 11px; text-transform: uppercase; margin-bottom: 5px;">' . implode(', ', $categoryNames) . '</div>';
        }

        if (!empty($page['tags'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['tags']);
            $html[] = '<div style="color: #007bff; font-size: 11px; text-transform: uppercase; margin-bottom: 5px;">' . implode(', ', $categoryNames) . '</div>';
        }

        // Compact title
        $html[] = '<h3 style="margin: 0 0 6px 0; font-size: 16px; line-height: 1.4;">';
        $html[] = '<a href="' . $url . '" style="color: #1a1a1a; text-decoration: none; font-weight: 600;">';
        $html[] = htmlspecialchars($title);
        $html[] = '</a>';
        $html[] = '</h3>';

        // Meta info
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

        // Render blocks if requested
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

        // Brief description
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
        Newsletter              $newsletter,
        Collection              $pages,
        NewsletterRenderContext $context,
        ?string                 $unsubscribeToken,
        bool                    $includeBlocks
    ): string
    {
        $html = [];

        $html[] = '<div style="max-width: 800px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #000000;">';

        // First page as dramatic hero (no promotions in hero)
        $featuredPage = $pages->first();
        if ($featuredPage) {
            $html[] = $this->renderHeroPage($featuredPage, $context);
            $pages = $pages->slice(1);
        }

        // Secondary articles with promotions
        if ($pages->count() > 0) {
            $html[] = '<div style="background: #ffffff; padding: 40px 20px;">';
            $html[] = '<h2 style="color: #333; margin: 0 0 30px 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #000; padding-bottom: 10px;">Also in this issue</h2>';

            // GET PROMOTIONS ONCE at the newsletter level
            $promotionBlocks = $this->injector->getBlocksForSurface(
                'newsletter_issue',
                $newsletter->id,
                $context->member,
                $context->siteId,
                'newsletter'
            );

            // Merge pages and promotions
            $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

            foreach ($allContent as $item) {
                if (isset($item['is_promotion']) && $item['is_promotion']) {
                    // Render promotion block
                    $rendered = $this->renderBlock($item, $context);
                    if ($rendered->wasRendered) {
                        $html[] = $rendered->html;
                    }
                } else {
                    // Render page card WITH blocks if requested
                    $html[] = $this->renderCompactCard($item, $context, $includeBlocks);
                }
            }

            $html[] = '</div>';
        }

        $html[] = $this->renderFooter($unsubscribeToken);
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

            // Categories and meta
            $metaItems = [];
            if (!empty($page['categories'])) {
                $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
                $metaItems[] = '<span style="color: rgba(255,255,255,0.9); font-size: 12px; text-transform: uppercase;">' . implode(' • ', $categoryNames) . '</span>';
            }
            if (!empty($page['tags'])) {
                $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['tags']);
                $metaItems[] = '<span style="color: rgba(255,255,255,0.9); font-size: 12px; text-transform: uppercase;">' . implode(' • ', $categoryNames) . '</span>';
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
            $html[] = '<a href="' . $url . '" style="color: #ffffff; text-decoration: none;">';
            $html[] = htmlspecialchars($title);
            $html[] = '</a>';
            $html[] = '</h1>';

            $metaDescription = $page['meta_description'] ?? null;
            if ($metaDescription) {
                $html[] = '<p style="color: rgba(255,255,255,0.95); font-size: 18px; line-height: 1.6; margin: 15px 0 20px 0; max-width: 600px; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">';
                $html[] = htmlspecialchars($metaDescription);
                $html[] = '</p>';
            }

            $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 14px 32px; background-color: #ffffff; color: #000000; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Read Now</a>';
            $html[] = '</div>';
        } else {
            $html[] = '<div style="padding: 60px 30px; background: #000000;">';

            // Meta info for no-image version
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
            $html[] = '<a href="' . $url . '" style="color: #ffffff; text-decoration: none;">';
            $html[] = htmlspecialchars($title);
            $html[] = '</a>';
            $html[] = '</h1>';

            $metaDescription = $page['meta_description'] ?? null;
            if ($metaDescription) {
                $html[] = '<p style="color: rgba(255,255,255,0.9); font-size: 20px; line-height: 1.6; margin: 0 0 30px 0; max-width: 600px;">';
                $html[] = htmlspecialchars($metaDescription);
                $html[] = '</p>';
            }

            $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 14px 32px; background-color: #ffffff; color: #000000; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Read Full Story</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }


    private function renderCompactCard(array $page, NewsletterRenderContext $context, bool $includeBlocks): string
    {
        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);

        $html = [];
        $html[] = '<div style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 2px solid #f0f0f0;">';

        // Meta bar
        $metaItems = [];
        if (!empty($page['categories'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $metaItems[] = '<span style="color: #007bff; font-size: 11px; text-transform: uppercase;">' . implode(', ', $categoryNames) . '</span>';
        }
        if (!empty($page['tags'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $metaItems[] = '<span style="color: #007bff; font-size: 11px; text-transform: uppercase;">' . implode(', ', $categoryNames) . '</span>';
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
        $html[] = '<a href="' . $url . '" style="color: #1a1a1a; text-decoration: none; font-weight: 700;">';
        $html[] = htmlspecialchars($title);
        $html[] = '</a>';
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
        Newsletter              $newsletter,
        Collection              $pages,
        NewsletterRenderContext $context,
        ?string                 $unsubscribeToken,
        bool                    $includeBlocks
    ): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Georgia, serif; background: #ffffff; padding: 40px 20px;">';

        // Minimal header
        $html[] = '<div style="text-align: center; border-bottom: 1px solid #000; padding-bottom: 20px; margin-bottom: 30px;">';
        $html[] = '<h1 style="font-size: 32px; font-weight: 400; margin: 0; color: #000;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="font-size: 12px; color: #666; margin: 10px 0 0 0; text-transform: uppercase; letter-spacing: 2px;">' . date('F j, Y') . '</p>';
        $html[] = '</div>';

        // GET PROMOTIONS ONCE at the newsletter level
        $promotionBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            $newsletter->id,
            $context->member,
            $context->siteId,
            'newsletter'
        );

        // Merge pages and promotions
        $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

        $html[] = '<div style="line-height: 1.8;">';
        foreach ($allContent as $item) {
            if (isset($item['is_promotion']) && $item['is_promotion']) {
                // Render promotion block
                $rendered = $this->renderBlock($item, $context);
                if ($rendered->wasRendered) {
                    $html[] = '<div style="margin-bottom: 25px;">';
                    $html[] = $rendered->html;
                    $html[] = '</div>';
                }
            } else {
                // Render simple page item
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

        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildDefaultTemplate(
        Newsletter              $newsletter,
        Collection              $pages,
        NewsletterRenderContext $context,
        ?string                 $unsubscribeToken,
        bool                    $includeBlocks
    ): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #ffffff;">';

        // Hero header section
        $html[] = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">';
        $html[] = '<h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 14px;">' . date('F j, Y') . '</p>';
        $html[] = '</div>';

        // Content container
        $html[] = '<div style="padding: 30px 20px;">';

        // GET PROMOTIONS ONCE at the newsletter level
        $promotionBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            $newsletter->id,
            $context->member,
            $context->siteId,
            'newsletter'
        );

        // Merge pages and promotions
        $allContent = $this->mergeContentAndPromotions($pages->toArray(), $promotionBlocks);

        foreach ($allContent as $item) {
            if (isset($item['is_promotion']) && $item['is_promotion']) {
                // Render promotion block
                $rendered = $this->renderBlock($item, $context);
                if ($rendered->wasRendered) {
                    $html[] = $rendered->html;
                }
            } else {
                // Render page card WITH blocks if requested
                $html[] = $this->renderPageCard($item, $context, $includeBlocks);
            }
        }

        $html[] = '</div>';

        // Footer
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderPageCard(array $page, NewsletterRenderContext $context, bool $includeBlocks): string
    {
        $pageId = $page['id'];
        $title = $page['title'];
        $slug = $page['slug'];
        $url = $this->buildTrackingUrl($pageId, $slug, $context->sendId, $context->includeTracking);

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

        // Category/Meta bar
        $html[] = '<div style="margin-bottom: 12px;">';
        $metaItems = [];

        if (!empty($page['categories'])) {
            $categoryNames = array_map(fn($cat) => htmlspecialchars($cat['name']), $page['categories']);
            $metaItems[] = '<span style="color: #667eea; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">' . implode(', ', $categoryNames) . '</span>';
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

        // Title
        $html[] = '<h2 style="margin: 0 0 12px 0; line-height: 1.3;">';
        $html[] = '<a href="' . $url . '" style="color: #1a1a1a; text-decoration: none; font-size: 22px; font-weight: 700;">';
        $html[] = htmlspecialchars($title);
        $html[] = '</a>';
        $html[] = '</h2>';

        // Tags
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

        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">Read More</a>';

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderFooter(?string $unsubscribeToken = null): string
    {
        $html = [];
        $html[] = '<div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999; font-size: 12px;">';
        $html[] = '<p>You received this email because you are subscribed to our newsletter.</p>';

        if ($unsubscribeToken) {
            $unsubscribeUrl = url("/member/subscriptions/unsubscribe/{$unsubscribeToken}");
            $html[] = '<p><a href="' . $unsubscribeUrl . '" style="color: #999;">Unsubscribe</a> | ';
            $manageUrl = url("/member/subscriptions/manage/{$unsubscribeToken}");
            $html[] = '<a href="' . $manageUrl . '" style="color: #999;">Manage Preferences</a></p>';
        }

        $html[] = '<p>&copy; ' . date('Y') . ' ' . config('app.name', 'Our Site') . '. All rights reserved.</p>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function mergeContentAndPromotions(array $pages, array $promotionBlocks): array
    {
        if (empty($promotionBlocks)) {
            // Convert pages to arrays but preserve blocks
            return array_map(function ($page) {
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
                    'blocks' => is_array($page->blocks) ? $page->blocks : $page->blocks->toArray(), // Preserve blocks as array
                ];
            }, $pages);
        }

        // Mark promotions
        foreach ($promotionBlocks as &$block) {
            $block['is_promotion'] = true;
        }

        // Convert pages to arrays preserving blocks
        $pagesArray = array_map(function ($page) {
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
                'blocks' => is_array($page->blocks) ? $page->blocks : $page->blocks->toArray(), // Preserve blocks as array
            ];
        }, $pages);

        // Simple interleaving: insert promotions every N pages
        $result = [];
        $promotionIndex = 0;
        $insertFrequency = max(2, (int)ceil(count($pagesArray) / (count($promotionBlocks) + 1)));

        for ($i = 0; $i < count($pagesArray); $i++) {
            $result[] = $pagesArray[$i];

            // Insert promotion after every N pages
            if (($i + 1) % $insertFrequency === 0 && $promotionIndex < count($promotionBlocks)) {
                $result[] = $promotionBlocks[$promotionIndex];
                $promotionIndex++;
            }
        }

        // Append any remaining promotions
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