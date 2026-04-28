<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\RecentlyViewedBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\RecommendationResolver;

class RecentlyViewedArticlesBlockRenderer implements EmailBlockRenderer
{
    public string $type = 'recently_viewed_articles';

    public function __construct(
        private readonly RecommendationResolver $resolver,
    )
    {
    }

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof RecentlyViewedBlockData) {
            return RenderedBlock::skipped();
        }

        $articles = $this->resolver->resolveArticles(
            $context->siteId,
            $blockData->limit,
            'recent',
            $context->member
        );

        if (empty($articles)) {
            return RenderedBlock::skipped();
        }

        return RenderedBlock::rendered(
            $this->buildHtml($blockData, $articles)
        );
    }

    // ── HTML ────────────────────────────────────────────────────────────────

    private function buildHtml(RecentlyViewedBlockData $blockData, array $articles): string
    {
        $baseWrapperStyle = 'margin:20px 0;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseWrapperStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if (!empty($blockData->title)) {
            $html[] = sprintf(
                '<h3 style="font-size:20px;font-weight:700;color:#1a1a1a;margin:0 0 16px 0;">%s</h3>',
                Str::sanitize($blockData->title)
            );
        }

        foreach ($articles as $article) {
            $html[] = $this->renderRow($article, $blockData);
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderRow(array $article, RecentlyViewedBlockData $blockData): string
    {
        $title = Str::sanitize($article['title'] ?? '');
        $slug = $article['slug'] ?? '';
        $imageUrl = $article['hero_image_url'] ?? null;
        $description = Str::sanitize($article['description'] ?? '');
        $url = $slug ? url('/' . ltrim($slug, '/')) : null;

        if (empty($title)) {
            return '';
        }

        $html = [];
        $html[] = '<div style="display:table;width:100%;margin-bottom:16px;">';

        if ($blockData->showImage && $imageUrl) {
            $html[] = sprintf(
                '<div style="display:table-cell;width:80px;padding-right:12px;vertical-align:top;">
                    <img src="%s" alt="%s" style="width:80px;height:80px;object-fit:cover;border-radius:4px;">
                </div>',
                Str::sanitize($imageUrl),
                $title
            );
        }

        $html[] = '<div style="display:table-cell;vertical-align:top;">';

        $html[] = sprintf(
            '<a href="%s" style="font-size:14px;font-weight:700;color:#1a1a1a;text-decoration:none;display:block;margin-bottom:6px;">%s</a>',
            $url ? Str::sanitize($url) : '#',
            $title
        );

        if (!empty($description)) {
            $excerpt = mb_strlen($description) > 100
                ? mb_substr($description, 0, 100) . '…'
                : $description;

            $html[] = sprintf(
                '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">%s</p>',
                $excerpt
            );
        }

        if ($url) {
            $html[] = sprintf(
                '<a href="%s" style="font-size:12px;color:#007bff;text-decoration:none;">Read again →</a>',
                Str::sanitize($url)
            );
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}