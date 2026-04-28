<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\ArticleRecommendationsBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\RecommendationResolver;

/**
 * Renders an article_recommendations block.
 *
 * At render time (send / preview-as-user) the RecommendationResolver fetches
 * real articles for the member.  When the resolver returns nothing the block
 * is silently skipped — a broken recommendations section is worse than no
 * section at all.
 *
 * The block never calls any API from Angular.  The frontend stores only the
 * block configuration (limit, fallback, display toggles) and a mock_items
 * array that is used exclusively by the builder preview.
 */
class ArticleRecommendationsBlockRenderer implements EmailBlockRenderer
{
    public string $type = 'article_recommendations';

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
        if (!$blockData instanceof ArticleRecommendationsBlockData) {
            return RenderedBlock::skipped();
        }

        $articles = $this->resolver->resolveArticles(
            siteId: $context->siteId,
            limit: $blockData->limit,
            fallback: $blockData->fallback,
            member: $context->member,
        );

        if (empty($articles)) {
            return RenderedBlock::skipped();
        }

        return RenderedBlock::rendered($this->buildHtml($blockData, $articles));
    }

    // ── HTML assembly ─────────────────────────────────────────────────────────

    private function buildHtml(ArticleRecommendationsBlockData $blockData, array $articles): string
    {
        $baseWrapperStyle = 'margin:20px 0;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseWrapperStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if (!empty($blockData->title)) {
            $titleStyle = 'font-size:20px;font-weight:700;color:#1a1a1a;margin:0 0 16px 0;padding-bottom:8px;border-bottom:2px solid #e9ecef;';
            $html[] = sprintf(
                '<h3 style="%s">%s</h3>',
                $titleStyle,
                Str::sanitize($blockData->title)
            );
        }

        foreach ($articles as $article) {
            $html[] = $this->renderArticleCard($article, $blockData->showImage, $blockData->showExcerpt);
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderArticleCard(array $article, bool $showImage, bool $showExcerpt): string
    {
        $title = Str::sanitize($article['title'] ?? '');
        $slug = $article['slug'] ?? '';
        $description = Str::sanitize($article['description'] ?? '');
        $imageUrl = $article['hero_image_url'] ?? null;
        $pageUrl = $slug ? url('/' . ltrim($slug, '/')) : null;

        if (empty($title)) {
            return '';
        }

        $cardStyle = 'display:table;width:100%;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #f0f0f0;';

        $html = [];
        $html[] = "<div style=\"{$cardStyle}\">";

        // Thumbnail
        if ($showImage && !empty($imageUrl)) {
            $html[] = '<div style="display:table-cell;width:100px;padding-right:16px;vertical-align:top;">';
            $imgTag = sprintf(
                '<img src="%s" alt="%s" width="100" height="75" style="width:100px;height:75px;object-fit:cover;border-radius:4px;display:block;">',
                Str::sanitize($imageUrl),
                $title
            );
            $html[] = $pageUrl
                ? '<a href="' . Str::sanitize($pageUrl) . '" style="display:block;">' . $imgTag . '</a>'
                : $imgTag;
            $html[] = '</div>';
        }

        // Body
        $html[] = '<div style="display:table-cell;vertical-align:top;">';

        // Title
        $titleHtml = sprintf(
            '<a href="%s" style="font-size:16px;font-weight:700;color:#1a1a1a;text-decoration:none;line-height:1.3;display:block;margin-bottom:6px;">%s</a>',
            $pageUrl ? Str::sanitize($pageUrl) : '#',
            $title
        );
        $html[] = $titleHtml;

        // Excerpt
        if ($showExcerpt && !empty($description)) {
            $excerpt = mb_strlen($description) > 120
                ? mb_substr($description, 0, 120) . '…'
                : $description;
            $html[] = sprintf(
                '<p style="margin:0 0 10px 0;font-size:13px;color:#666;line-height:1.5;">%s</p>',
                $excerpt
            );
        }

        // Read more
        if ($pageUrl) {
            $html[] = sprintf(
                '<a href="%s" style="font-size:12px;font-weight:600;color:#007bff;text-decoration:none;">Read more →</a>',
                Str::sanitize($pageUrl)
            );
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}