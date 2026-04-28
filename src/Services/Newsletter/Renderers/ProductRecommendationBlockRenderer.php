<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\ArticleRecommendationsBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ProductRecommendationsBlockData;
use App\Services\Newsletter\DTOs\BlockData\RecentlyViewedBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\RecommendationResolver;

class ProductRecommendationBlockRenderer implements EmailBlockRenderer
{
    public string $type = 'product_recommendations';

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
        if (!$blockData instanceof ProductRecommendationsBlockData) {
            return RenderedBlock::skipped();
        }

        $products = $this->resolver->resolveProducts(
            siteId: $context->siteId,
            limit: $blockData->limit,
            fallback: $blockData->fallback,
            member: $context->member,
        );

        if (empty($products)) {
            return RenderedBlock::skipped();
        }

        return RenderedBlock::rendered($this->buildHtml($blockData, $products));
    }

    private function buildHtml(ProductRecommendationsBlockData $blockData, array $products): string
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

        // Responsive grid: up to 4 items in a single table row, each cell equal width
        $count = count($products);
        $columns = min($count, 4);
        $cellWidth = floor(100 / $columns);

        $html[] = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
        $html[] = '<tr>';

        foreach ($products as $index => $product) {
            if ($index > 0 && $index % $columns === 0) {
                $html[] = '</tr><tr>';
            }
            $html[] = $this->renderProductCell($product, $cellWidth, $blockData->showImage, $blockData->showPrice);
        }

        $html[] = '</tr>';
        $html[] = '</table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderProductCell(
        array $product,
        int   $cellWidth,
        bool  $showImage,
        bool  $showPrice,
    ): string
    {
        $name = Str::sanitize($product['name'] ?? '');
        $imageUrl = $product['image_url'] ?? null;
        $link = $product['link'] ?? null;
        $price = (float)($product['price'] ?? 0);
        $currency = Str::sanitize($product['currency'] ?? '£');

        if (empty($name)) {
            return '';
        }

        $cellStyle = "width:{$cellWidth}%;padding:8px;vertical-align:top;text-align:center;";

        $html = [];
        $html[] = "<td style=\"{$cellStyle}\">";
        $html[] = '<div style="border:1px solid #e9ecef;border-radius:6px;overflow:hidden;background:#fff;">';

        // Image
        if ($showImage && !empty($imageUrl)) {
            $imgTag = sprintf(
                '<img src="%s" alt="%s" width="100%%" style="width:100%%;height:140px;object-fit:cover;display:block;">',
                Str::sanitize($imageUrl),
                $name
            );
            $html[] = $link
                ? '<a href="' . Str::sanitize($link) . '" style="display:block;">' . $imgTag . '</a>'
                : $imgTag;
        }

        $html[] = '<div style="padding:10px;">';

        // Name
        $nameHtml = $link
            ? sprintf(
                '<a href="%s" style="font-size:13px;font-weight:600;color:#1a1a1a;text-decoration:none;line-height:1.4;display:block;">%s</a>',
                Str::sanitize($link),
                $name
            )
            : sprintf('<span style="font-size:13px;font-weight:600;color:#1a1a1a;display:block;">%s</span>', $name);

        $html[] = $nameHtml;

        // Price
        if ($showPrice && $price > 0) {
            $html[] = sprintf(
                '<div style="font-size:14px;font-weight:700;color:#333;margin-top:6px;">%s%s</div>',
                $currency,
                number_format($price, 2)
            );
        }

        // CTA
        if ($link) {
            $html[] = sprintf(
                '<a href="%s" style="display:inline-block;margin-top:8px;padding:6px 14px;background:#007bff;color:#fff;font-size:12px;font-weight:600;text-decoration:none;border-radius:4px;">View</a>',
                Str::sanitize($link)
            );
        }

        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</td>';

        return implode("\n", $html);
    }


}