<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Logger;
use App\Framework\Support\Str;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\ArticleCardBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

/**
 * Renders an article_card block.
 *
 * Rendering priority:
 *   1. Fetch the live page from PageRepository using page_id.
 *   2. If the page is not found (deleted / unpublished), fall back to the
 *      cached fields stored in the block data at insert time.
 *   3. If page_id is null and there are no cached fields, skip the block.
 *
 * This renderer intentionally does NOT throw on a missing page — email
 * delivery is a critical flow and a single deleted article must not break
 * an entire send.
 */
class ArticleCardBlockRenderer implements EmailBlockRenderer
{
    public string $type = 'article_card';

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly Logger         $logger,
    )
    {
    }

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof ArticleCardBlockData) {
            return RenderedBlock::skipped();
        }

        // ── Resolve display data ──────────────────────────────────────────────
        $title = $blockData->title;
        $slug = $blockData->slug;
        $description = $blockData->description;
        $imageUrl = $blockData->heroImageUrl;
        $pageUrl = null;

        if ($blockData->pageId !== null) {
            try {
                $page = $this->pageRepository->find($blockData->pageId);

                if ($page !== null) {
                    // Live data takes precedence over cached fields
                    $title = $page->title ?: $title;
                    $slug = $page->slug ?: $slug;
                    $description = $page->description ?: $description;
                    $imageUrl = $page->forms?->main?->hero_image_url ?? $imageUrl;
                    $pageUrl = url('/' . ltrim($slug, '/'));
                } else {
                    $this->logger->warning('ArticleCardBlockRenderer: page not found, using cached fields', [
                        'page_id' => $blockData->pageId,
                    ]);
                    // Fall through to cached fields already set above
                    if ($slug) {
                        $pageUrl = url('/' . ltrim($slug, '/'));
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error('ArticleCardBlockRenderer: page fetch failed', [
                    'page_id' => $blockData->pageId,
                    'error' => $e->getMessage(),
                ]);
                if ($slug) {
                    $pageUrl = url('/' . ltrim($slug, '/'));
                }
            }
        } elseif ($slug) {
            $pageUrl = url('/' . ltrim($slug, '/'));
        }

        // Nothing to render — no page reference and no cached title
        if (empty($title) && empty($slug)) {
            return RenderedBlock::skipped();
        }

        // ── Build HTML ────────────────────────────────────────────────────────
        $alignStyle = match ($blockData->align) {
            'center' => 'text-align:center;',
            'right' => 'text-align:right;',
            default => 'text-align:left;',
        };

        $baseWrapperStyle = "margin:20px 0;padding:16px;border:1px solid #e9ecef;border-radius:6px;{$alignStyle}";
        $wrapperStyle = $blockData->style->mergeIntoCss($baseWrapperStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        // Hero image
        if ($blockData->showImage && !empty($imageUrl)) {
            $imgTag = sprintf(
                '<img src="%s" alt="%s" style="width:100%%;max-width:100%%;height:auto;border-radius:4px;display:block;margin-bottom:12px;">',
                Str::sanitize($imageUrl),
                Str::sanitize($title),
            );
            $html[] = $pageUrl
                ? '<a href="' . Str::sanitize($pageUrl) . '" style="display:block;">' . $imgTag . '</a>'
                : $imgTag;
        }

        // Title
        $baseTitleStyle = 'margin:0 0 8px 0;font-size:18px;font-weight:700;color:#1a1a1a;line-height:1.3;';
        $titleStyle = $blockData->style->mergeIntoCss($baseTitleStyle);

        if ($pageUrl) {
            $html[] = sprintf(
                '<h3 style="%s"><a href="%s" style="color:inherit;text-decoration:none;">%s</a></h3>',
                $titleStyle,
                Str::sanitize($pageUrl),
                Str::sanitize($title),
            );
        } else {
            $html[] = sprintf('<h3 style="%s">%s</h3>', $titleStyle, Str::sanitize($title));
        }

        // Description
        if ($blockData->showDescription && !empty($description)) {
            $baseDescStyle = 'margin:0 0 12px 0;font-size:14px;color:#555;line-height:1.6;';
            $descStyle = $blockData->style->mergeIntoCss($baseDescStyle);
            $truncated = mb_strlen($description) > 200
                ? mb_substr($description, 0, 200) . '…'
                : $description;
            $html[] = sprintf('<p style="%s">%s</p>', $descStyle, Str::sanitize($truncated));
        }

        // Read-more link
        if ($pageUrl) {
            $html[] = sprintf(
                '<a href="%s" style="display:inline-block;padding:8px 16px;background:#007bff;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;font-weight:600;">Read article</a>',
                Str::sanitize($pageUrl),
            );
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}