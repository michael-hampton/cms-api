<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Models\Newsletter;
use App\Models\Page;
use App\Repositories\PageRepository;

class NewsletterPageBuilderService
{
    public function __construct(
        private readonly PageRepository $pageRepository
    )
    {
    }

    /**
     * Get pages for newsletter based on filters
     */
    public function getPagesForNewsletter(Newsletter $newsletter, int $siteId): Collection
    {
        if (!$newsletter->isAutomated()) {
            return collect([]);
        }

        $filters = $newsletter->page_filters ?? [];

        // Build query for published pages
        $query = Page::with(['categories', 'tags', 'authors', 'metadata'])
            ->where('site_id', $siteId)
            ->where('status', 'published');

        // Apply date range filter (e.g., pages published since last newsletter)
        if ($newsletter->last_sent) {
            $query->where('published_at', '>=', $newsletter->last_sent->format('Y-m-d H:i:s'));
        } elseif (isset($filters['date_range_days'])) {
            $query->where('published_at', '>=', date('Y-m-d H:i:s', strtotime("-{$filters['date_range_days']} days")));
        }

        // Filter by categories
        if (!empty($filters['categories'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->whereIn('categories.id', $filters['categories']);
            });
        }

        // Filter by tags
        if (!empty($filters['tags'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('tags.id', $filters['tags']);
            });
        }

        // Filter by page type
        if (!empty($filters['page_types'])) {
            $query->whereIn('page_type', $filters['page_types']);
        }

        // Filter by featured status
        if (isset($filters['featured_only']) && $filters['featured_only']) {
            $query->whereHas('metadata', function ($q) {
                $q->where('featured', true);
            });
        }

        // Apply sorting
        $sortBy = $newsletter->sort_by ?? 'published_at';
        $sortOrder = $newsletter->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Apply limit
        if ($newsletter->max_pages) {
            $query->limit($newsletter->max_pages);
        }

        return $query->get();
    }

    /**
     * Build newsletter HTML from pages
     */
    public function buildNewsletterHtml(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken = null, bool $includeBlocks = false): string
    {
        $template = $newsletter->template ?? 'default';

        switch ($template) {
            case 'digest':
                return $this->buildDigestTemplate($newsletter, $pages, $unsubscribeToken);
            case 'featured':
                return $this->buildFeaturedTemplate($newsletter, $pages, $unsubscribeToken);
            case 'simple':
                return $this->buildSimpleTemplate($newsletter, $pages, $unsubscribeToken);
            default:
                return $this->buildDefaultTemplate($newsletter, $pages, $unsubscribeToken, $includeBlocks);
        }
    }

    /**
     * Build newsletter HTML from page blocks
     */
    public function buildNewsletterHtmlFromBlocks(Newsletter $newsletter, Page $page, ?string $unsubscribeToken = null): string
    {
        $blocks = $page->blocks;

        if ($blocks->isEmpty()) {
            $blocks = [];
        }

        $html = [];
        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">';
        $html[] = '<div style="background: white; padding: 30px; border-radius: 8px;">';

        foreach ($blocks as $block) {
            $html[] = $this->renderBlockForEmail($block->toArray());
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render a single block for email
     */
    private function renderBlockForEmail(array $block): string
    {
        $type = $block['type'] ?? 'text';
        $blockData = $block['data'] ?? [];

        return match ($type) {
            'text' => $this->renderTextBlock($blockData),
            'heading' => $this->renderHeadingBlock($blockData),
            'image' => $this->renderImageBlock($blockData),
            'quote' => $this->renderQuoteBlock($blockData),
            'list' => $this->renderListBlock($blockData),
            'cta' => $this->renderCtaBlock($blockData),
            'divider' => $this->renderDividerBlock($blockData),
            'banner' => $this->renderBannerBlock($blockData),
            'hero' => $this->renderHeroBlock($blockData),
            'info' => $this->renderInfoBlock($blockData),
            'product' => $this->renderProductBlock($blockData),
            'section' => $this->renderSectionBlock($blockData),
            'table' => $this->renderTableBlock($blockData),
            'person' => $this->renderPersonBlock($blockData),
            'product-comparison' => $this->renderProductComparisonBlock($blockData),
            'schema' => $this->renderSchemaBlock($blockData),
            'stats' => $this->renderStatsBlock($blockData),
            'testimonial' => $this->renderTestimonialBlock($blockData),
            'award' => $this->renderAwardBlock($blockData),
            'note' => $this->renderBoxoutBlock($blockData),
            'buying-guide' => $this->renderBuyingGuideBlock($blockData),
            'contact-form' => $this->renderContactFormBlock($blockData),
            'deal' => $this->renderDealBlock($blockData),
            default => ''
        };
    }

    /**
     * Render award block for email
     */
    private function renderAwardBlock(array $block): string
    {
        $subcategory = htmlspecialchars($block['subcategory'] ?? '');
        $productName = htmlspecialchars($block['productName'] ?? '');
        $caption = htmlspecialchars($block['caption'] ?? '');
        $strapline = htmlspecialchars($block['strapline'] ?? '');
        $rating = (float)($block['rating'] ?? 0);
        $winner = (bool)($block['winner'] ?? false);
        $imageSrc = htmlspecialchars($block['image']['src'] ?? '');

        $html = [];
        $html[] = '<div style="border: 2px solid ' . ($winner ? '#FFD700' : '#ddd') . '; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: ' . ($winner ? '#fffef0' : '#ffffff') . ';">';

        if ($winner) {
            $html[] = '<div style="background-color: #FFD700; color: #333; padding: 8px 16px; border-radius: 4px; display: inline-block; font-weight: bold; margin-bottom: 15px;">🏆 Winner</div>';
        }

        $html[] = '<div style="display: table; width: 100%;">';

        if (!empty($imageSrc)) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 150px; padding-right: 20px;">';
            $html[] = "<img src=\"{$imageSrc}\" alt=\"{$productName}\" style=\"width: 150px; height: auto; border-radius: 4px;\">";
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';
        $html[] = "<div style=\"color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;\">{$subcategory}</div>";
        $html[] = "<h3 style=\"color: #333; margin: 0 0 10px 0; font-size: 20px;\">{$productName}</h3>";

        if (!empty($strapline)) {
            $html[] = "<p style=\"color: #666; margin: 0 0 10px 0; font-size: 14px; font-style: italic;\">{$strapline}</p>";
        }

        if ($rating > 0) {
            $stars = str_repeat('⭐', (int)$rating);
            $html[] = "<div style=\"color: #ffc107; margin-bottom: 10px;\">{$stars} {$rating}/5</div>";
        }

        if (!empty($caption)) {
            $html[] = "<p style=\"color: #333; margin: 0; font-size: 14px; line-height: 1.6;\">{$caption}</p>";
        }

        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render boxout/note block for email
     */
    private function renderBoxoutBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $paragraphs = $block['paragraphs'] ?? [];
        $linkUrl = htmlspecialchars($block['linkUrl'] ?? '');
        $linkText = htmlspecialchars($block['linkText'] ?? 'Learn More');
        $imageSrc = htmlspecialchars($block['image']['src'] ?? '');
        $sponsored = (bool)($block['sponsored'] ?? false);

        $html = [];
        $html[] = '<div style="background-color: #f8f9fa; border-left: 4px solid #007bff; border-radius: 4px; padding: 20px; margin: 20px 0;">';

        if (!empty($imageSrc)) {
            $html[] = "<img src=\"{$imageSrc}\" alt=\"{$title}\" style=\"max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;\">";
        }

        $html[] = "<h3 style=\"color: #333; margin: 0 0 15px 0; font-size: 20px;\">{$title}</h3>";

        foreach ($paragraphs as $paragraph) {
            $paragraphText = htmlspecialchars($paragraph);
            $html[] = "<p style=\"color: #333; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;\">{$paragraphText}</p>";
        }

        if (!empty($linkUrl)) {
            $html[] = '<a href="' . $linkUrl . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = $linkText;
            if ($sponsored) {
                $html[] = ' <span style="background-color: #ffc107; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Sponsored</span>';
            }
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render buying guide block for email
     */
    private function renderBuyingGuideBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $subtitle = htmlspecialchars($block['subtitle'] ?? '');
        $specs = $block['specs'] ?? [];
        $pros = $block['pros'] ?? [];
        $cons = $block['cons'] ?? [];
        $url = htmlspecialchars($block['url'] ?? '');
        $linkText = htmlspecialchars($block['linkText'] ?? 'Learn More');
        $imageSrc = htmlspecialchars($block['image']['src'] ?? '');
        $sponsored = (bool)($block['sponsored'] ?? false);
        $showReviewPanel = (bool)($block['showReviewPanel'] ?? false);

        $html = [];
        $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">';

        if ($sponsored) {
            $html[] = '<span style="background-color: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-bottom: 15px;">Sponsored</span>';
        }

        if (!empty($imageSrc)) {
            $html[] = "<img src=\"{$imageSrc}\" alt=\"{$title}\" style=\"max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;\">";
        }

        $html[] = "<h3 style=\"color: #333; margin: 0 0 10px 0; font-size: 22px;\">{$title}</h3>";

        if (!empty($subtitle)) {
            $html[] = "<p style=\"color: #666; margin: 0 0 20px 0; font-size: 16px;\">{$subtitle}</p>";
        }

        // Specs
        if (!empty($specs)) {
            $html[] = '<h4 style="color: #333; margin: 20px 0 10px 0; font-size: 18px;">Specifications</h4>';
            $html[] = '<table style="width: 100%; border-collapse: collapse;">';
            foreach ($specs as $spec) {
                $specText = htmlspecialchars($spec['text'] ?? '');
                $specValue = htmlspecialchars($spec['value'] ?? '');
                $html[] = '<tr>';
                $html[] = "<td style=\"padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; color: #333;\">{$specText}</td>";
                $html[] = "<td style=\"padding: 8px; border-bottom: 1px solid #eee; color: #666;\">{$specValue}</td>";
                $html[] = '</tr>';
            }
            $html[] = '</table>';
        }

        // Pros and Cons
        if ($showReviewPanel && (!empty($pros) || !empty($cons))) {
            $html[] = '<div style="margin-top: 20px;">';
            $html[] = '<table style="width: 100%;">';
            $html[] = '<tr>';

            if (!empty($pros)) {
                $html[] = '<td style="width: 50%; padding-right: 10px; vertical-align: top;">';
                $html[] = '<h5 style="color: #28a745; margin: 0 0 10px 0;">✓ Advantages</h5>';
                $html[] = '<ul style="margin: 0; padding-left: 20px; color: #333;">';
                foreach ($pros as $pro) {
                    $html[] = '<li style="margin-bottom: 5px;">' . htmlspecialchars($pro) . '</li>';
                }
                $html[] = '</ul>';
                $html[] = '</td>';
            }

            if (!empty($cons)) {
                $html[] = '<td style="width: 50%; padding-left: 10px; vertical-align: top;">';
                $html[] = '<h5 style="color: #dc3545; margin: 0 0 10px 0;">✗ Considerations</h5>';
                $html[] = '<ul style="margin: 0; padding-left: 20px; color: #333;">';
                foreach ($cons as $con) {
                    $html[] = '<li style="margin-bottom: 5px;">' . htmlspecialchars($con) . '</li>';
                }
                $html[] = '</ul>';
                $html[] = '</td>';
            }

            $html[] = '</tr>';
            $html[] = '</table>';
            $html[] = '</div>';
        }

        if (!empty($url)) {
            $html[] = '<div style="margin-top: 20px;">';
            $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = $linkText;
            $html[] = '</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render contact form block for email
     */
    private function renderContactFormBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $subtitle = htmlspecialchars($block['subtitle'] ?? '');
        $contactInfo = $block['contact_info'] ?? [];

        // For newsletters, we display contact info rather than the form
        $html = [];
        $html[] = '<div style="background-color: #f8f9fa; border-radius: 8px; padding: 25px; margin: 20px 0;">';

        $html[] = "<h3 style=\"color: #333; margin: 0 0 10px 0; font-size: 22px;\">{$title}</h3>";

        if (!empty($subtitle)) {
            $html[] = "<p style=\"color: #666; margin: 0 0 20px 0; font-size: 16px;\">{$subtitle}</p>";
        }

        if (!empty($contactInfo['email'])) {
            $html[] = '<div style="margin-bottom: 15px;">';
            $html[] = '<span style="font-size: 20px; margin-right: 10px;">✉️</span>';
            $html[] = '<strong style="color: #333;">Email:</strong> ';
            $html[] = '<a href="mailto:' . htmlspecialchars($contactInfo['email']) . '" style="color: #007bff; text-decoration: none;">' . htmlspecialchars($contactInfo['email']) . '</a>';
            $html[] = '</div>';
        }

        if (!empty($contactInfo['phone'])) {
            $html[] = '<div style="margin-bottom: 15px;">';
            $html[] = '<span style="font-size: 20px; margin-right: 10px;">📞</span>';
            $html[] = '<strong style="color: #333;">Phone:</strong> ';
            $html[] = '<a href="tel:' . htmlspecialchars($contactInfo['phone']) . '" style="color: #007bff; text-decoration: none;">' . htmlspecialchars($contactInfo['phone']) . '</a>';
            $html[] = '</div>';
        }

        if (!empty($contactInfo['address'])) {
            $address = $contactInfo['address'];
            $html[] = '<div style="margin-bottom: 15px;">';
            $html[] = '<span style="font-size: 20px; margin-right: 10px;">📍</span>';
            $html[] = '<strong style="color: #333;">Address:</strong><br>';
            $html[] = '<span style="color: #666; margin-left: 30px;">';
            $html[] = htmlspecialchars($address['line1'] ?? '');
            if (!empty($address['line2'])) {
                $html[] = '<br>' . htmlspecialchars($address['line2']);
            }
            $html[] = '<br>' . htmlspecialchars($address['city'] ?? '') . ', ' . htmlspecialchars($address['postcode'] ?? '');
            $html[] = '</span>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render deal block for email
     */
    private function renderDealBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $productName = htmlspecialchars($block['productName'] ?? '');
        $brand = htmlspecialchars($block['brand'] ?? '');
        $description = htmlspecialchars($block['description'] ?? '');
        $price = (float)($block['price'] ?? 0);
        $salePrice = (float)($block['salePrice'] ?? 0);
        $currency = htmlspecialchars($block['currency'] ?? '£');
        $savings = $block['savings'] ?? 0;
        $savingsPercent = $block['savings_percent'] ?? 0;
        $link = htmlspecialchars($block['link'] ?? '');
        $imageSrc = htmlspecialchars($block['image']['src'] ?? '');
        $sponsored = (bool)($block['sponsored'] ?? false);
        $voucherId = htmlspecialchars($block['voucherId'] ?? '');
        $hasSavings = $salePrice < $price;

        $html = [];
        $html[] = '<div style="border: 2px solid #ff4757; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: #fff5f5;">';

        $html[] = '<div style="margin-bottom: 15px;">';
        if ($sponsored) {
            $html[] = '<span style="background-color: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 10px;">Sponsored</span>';
        }
        if (!empty($voucherId)) {
            $html[] = '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">🎟️ Voucher Available</span>';
        }
        $html[] = '</div>';

        $html[] = '<div style="display: table; width: 100%;">';

        if (!empty($imageSrc)) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 150px; padding-right: 20px;">';
            $html[] = "<img src=\"{$imageSrc}\" alt=\"{$productName}\" style=\"width: 150px; height: auto; border-radius: 4px;\">";
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';
        $html[] = "<h3 style=\"color: #333; margin: 0 0 5px 0; font-size: 20px;\">{$title}</h3>";

        if (!empty($brand)) {
            $html[] = "<div style=\"color: #666; font-size: 14px; margin-bottom: 5px;\">{$brand}</div>";
        }

        $html[] = "<h4 style=\"color: #333; margin: 0 0 10px 0; font-size: 16px;\">{$productName}</h4>";

        if (!empty($description)) {
            $html[] = "<p style=\"color: #666; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;\">{$description}</p>";
        }

        // Pricing
        $html[] = '<div style="margin-bottom: 15px;">';
        if ($hasSavings) {
            $html[] = "<span style=\"color: #999; text-decoration: line-through; font-size: 16px; margin-right: 10px;\">{$currency}{$price}</span>";
            $html[] = "<span style=\"color: #ff4757; font-size: 24px; font-weight: bold;\">{$currency}{$salePrice}</span>";
            $html[] = "<div style=\"color: #28a745; font-size: 14px; font-weight: bold; margin-top: 5px;\">Save {$currency}{$savings} ({$savingsPercent}%)</div>";
        } else {
            $html[] = "<span style=\"color: #333; font-size: 24px; font-weight: bold;\">{$currency}{$price}</span>";
        }
        $html[] = '</div>';

        // Voucher code
        if (!empty($voucherId)) {
            $html[] = '<div style="background-color: white; border: 2px dashed #28a745; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            $html[] = '<span style="color: #666; font-size: 12px;">Use Code:</span> ';
            $html[] = '<span style="color: #333; font-size: 16px; font-weight: bold; font-family: monospace;">' . $voucherId . '</span>';
            $html[] = '</div>';
        }

        if (!empty($link)) {
            $html[] = '<a href="' . $link . '" style="display: inline-block; padding: 12px 30px; background-color: #ff4757; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">Get Deal</a>';
        }

        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render text block for email
     */
    private function renderTextBlock(array $block): string
    {
        $paragraphs = $block['paragraphs'] ?? [];
        $html = [];

        foreach ($paragraphs as $paragraph) {
            $html[] = '<p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">';
            $html[] = htmlspecialchars($paragraph);
            $html[] = '</p>';
        }

        return implode("\n", $html);
    }

    /**
     * Render heading block for email
     */
    private function renderHeadingBlock(array $block): string
    {
        $text = htmlspecialchars($block['text'] ?? '');
        $subtitle = htmlspecialchars($block['subtitle'] ?? '');
        $level = $block['level'] ?? 2;

        // Map heading level to appropriate font sizes for email
        $sizes = [
            1 => '32px',
            2 => '28px',
            3 => '24px',
            4 => '20px',
            5 => '18px',
            6 => '16px'
        ];
        $size = $sizes[$level] ?? '24px';

        $html = [];
        $html[] = "<h{$level} style=\"color: #333; font-size: {$size}; margin: 20px 0 10px 0; font-weight: bold;\">";
        $html[] = $text;
        $html[] = "</h{$level}>";

        if (!empty($subtitle)) {
            $html[] = '<p style="color: #666; font-size: 16px; margin: 0 0 20px 0;">';
            $html[] = $subtitle;
            $html[] = '</p>';
        }

        return implode("\n", $html);
    }

    /**
     * Render image block for email
     */
    private function renderImageBlock(array $block): string
    {
        $src = htmlspecialchars($block['src'] ?? '');
        $alt = htmlspecialchars($block['alt'] ?? '');
        $caption = htmlspecialchars($block['caption'] ?? '');
        $linkUrl = htmlspecialchars($block['linkUrl'] ?? '');

        $html = [];
        $html[] = '<div style="margin: 20px 0;">';

        $imgTag = "<img src=\"{$src}\" alt=\"{$alt}\" style=\"max-width: 100%; height: auto; display: block;\">";

        if (!empty($linkUrl)) {
            $html[] = "<a href=\"{$linkUrl}\">{$imgTag}</a>";
        } else {
            $html[] = $imgTag;
        }

        if (!empty($caption)) {
            $html[] = '<p style="color: #666; font-size: 14px; font-style: italic; margin: 10px 0 0 0;">';
            $html[] = $caption;
            $html[] = '</p>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render quote block for email
     */
    private function renderQuoteBlock(array $block): string
    {
        $text = htmlspecialchars($block['text'] ?? '');
        $attribution = htmlspecialchars($block['attribution'] ?? '');

        $html = [];
        $html[] = '<blockquote style="border-left: 4px solid #007bff; padding-left: 20px; margin: 20px 0; font-style: italic;">';
        $html[] = '<p style="color: #333; font-size: 18px; line-height: 1.6; margin: 0;">';
        $html[] = $text;
        $html[] = '</p>';

        if (!empty($attribution)) {
            $html[] = '<cite style="color: #666; font-size: 14px; font-style: normal; display: block; margin-top: 10px;">';
            $html[] = '— ' . $attribution;
            $html[] = '</cite>';
        }

        $html[] = '</blockquote>';

        return implode("\n", $html);
    }

    /**
     * Render list block for email
     */
    private function renderListBlock(array $block): string
    {
        $listType = $block['listType'] ?? 'ul';
        $items = $block['items'] ?? [];

        $html = [];
        $html[] = "<{$listType} style=\"color: #333; font-size: 16px; line-height: 1.6; margin: 15px 0; padding-left: 30px;\">";

        foreach ($items as $item) {
            $html[] = '<li style="margin-bottom: 8px;">';
            $html[] = htmlspecialchars($item);
            $html[] = '</li>';
        }

        $html[] = "</{$listType}>";

        return implode("\n", $html);
    }

    /**
     * Render CTA block for email
     */
    private function renderCtaBlock(array $block): string
    {
        $text = htmlspecialchars($block['text'] ?? 'Click Here');
        $url = htmlspecialchars($block['url'] ?? '#');
        $alignment = $block['alignment'] ?? 'center';

        $alignStyle = match ($alignment) {
            'left' => 'text-align: left;',
            'right' => 'text-align: right;',
            default => 'text-align: center;'
        };

        $html = [];
        $html[] = "<div style=\"margin: 25px 0; {$alignStyle}\">";
        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">';
        $html[] = $text;
        $html[] = '</a>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render divider block for email
     */
    private function renderDividerBlock(array $block): string
    {
        $style = $block['style'] ?? 'solid';

        $borderStyle = match ($style) {
            'dashed' => 'dashed',
            'dotted' => 'dotted',
            'double' => 'double',
            default => 'solid'
        };

        return "<hr style=\"border: none; border-top: 2px {$borderStyle} #ddd; margin: 25px 0;\">";
    }

    /**
     * Render banner block for email
     */
    private function renderBannerBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $subtitle = htmlspecialchars($block['subtitle'] ?? '');
        $ctaText = htmlspecialchars($block['ctaText'] ?? '');
        $ctaUrl = htmlspecialchars($block['ctaUrl'] ?? '');
        $backgroundColor = $block['backgroundColor'] ?? '#007bff';
        $textColor = $block['textColor'] ?? '#ffffff';

        $html = [];
        $html[] = "<div style=\"background-color: {$backgroundColor}; color: {$textColor}; padding: 25px; border-radius: 8px; margin: 20px 0;\">";
        $html[] = "<h3 style=\"color: {$textColor}; margin: 0 0 10px 0; font-size: 24px;\">{$title}</h3>";

        if (!empty($subtitle)) {
            $html[] = "<p style=\"color: {$textColor}; margin: 0 0 15px 0; font-size: 16px;\">{$subtitle}</p>";
        }

        if (!empty($ctaText) && !empty($ctaUrl)) {
            $html[] = '<a href="' . $ctaUrl . '" style="display: inline-block; padding: 10px 20px; background-color: white; color: ' . $backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = $ctaText;
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render hero block for email (simplified)
     */
    private function renderHeroBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $subtitle = htmlspecialchars($block['subtitle'] ?? '');
        $ctaText = htmlspecialchars($block['ctaText'] ?? '');
        $ctaUrl = htmlspecialchars($block['ctaUrl'] ?? '');

        $html = [];
        $html[] = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; border-radius: 8px; text-align: center; margin: 20px 0;">';
        $html[] = "<h1 style=\"color: white; margin: 0 0 15px 0; font-size: 32px;\">{$title}</h1>";

        if (!empty($subtitle)) {
            $html[] = "<p style=\"color: white; margin: 0 0 20px 0; font-size: 18px;\">{$subtitle}</p>";
        }

        if (!empty($ctaText) && !empty($ctaUrl)) {
            $html[] = '<a href="' . $ctaUrl . '" style="display: inline-block; padding: 12px 30px; background-color: white; color: #667eea; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">';
            $html[] = $ctaText;
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render info block for email
     */
    private function renderInfoBlock(array $block): string
    {
        $infoType = $block['infoType'] ?? 'info';
        $description = htmlspecialchars($block['description'] ?? '');

        $colors = [
            'info' => ['bg' => '#e7f3ff', 'border' => '#007bff'],
            'warning' => ['bg' => '#fff3cd', 'border' => '#ffc107'],
            'tip' => ['bg' => '#d4edda', 'border' => '#28a745'],
            'note' => ['bg' => '#f8f9fa', 'border' => '#6c757d']
        ];

        $color = $colors[$infoType] ?? $colors['info'];

        $html = [];
        $html[] = "<div style=\"background-color: {$color['bg']}; border-left: 4px solid {$color['border']}; padding: 15px; margin: 20px 0; border-radius: 4px;\">";
        $html[] = "<p style=\"margin: 0; color: #333; font-size: 16px; line-height: 1.6;\">";
        $html[] = '<strong>' . ucfirst($infoType) . ':</strong> ' . $description;
        $html[] = '</p>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render product block for email
     */
    private function renderProductBlock(array $block): string
    {
        $name = htmlspecialchars($block['name'] ?? '');
        $description = htmlspecialchars($block['description'] ?? '');
        $price = $block['price'] ?? 0;
        $salePrice = $block['salePrice'] ?? 0;
        $currency = $block['currency'] ?? '$';
        $link = htmlspecialchars($block['link'] ?? '');
        $linkText = htmlspecialchars($block['linkText'] ?? 'View Product');
        $imageSrc = htmlspecialchars($block['image']['src'] ?? '');

        $html = [];
        $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">';

        if (!empty($imageSrc)) {
            $html[] = "<img src=\"{$imageSrc}\" alt=\"{$name}\" style=\"max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;\">";
        }

        $html[] = "<h3 style=\"color: #333; margin: 0 0 10px 0; font-size: 20px;\">{$name}</h3>";

        if (!empty($description)) {
            $html[] = "<p style=\"color: #666; margin: 0 0 15px 0; font-size: 14px;\">{$description}</p>";
        }

        $html[] = '<div style="margin: 15px 0;">';
        if ($salePrice > 0 && $salePrice < $price) {
            $html[] = "<span style=\"color: #999; text-decoration: line-through; margin-right: 10px;\">{$currency}{$price}</span>";
            $html[] = "<span style=\"color: #d9534f; font-size: 20px; font-weight: bold;\">{$currency}{$salePrice}</span>";
        } else {
            $html[] = "<span style=\"color: #333; font-size: 20px; font-weight: bold;\">{$currency}{$price}</span>";
        }
        $html[] = '</div>';

        if (!empty($link)) {
            $html[] = '<a href="' . $link . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = $linkText;
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render section block for email
     */
    private function renderSectionBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $headingType = $block['headingType'] ?? 'h2';

        $sizes = [
            'h1' => '32px',
            'h2' => '28px',
            'h3' => '24px',
            'h4' => '20px',
            'h5' => '18px',
            'h6' => '16px'
        ];
        $size = $sizes[$headingType] ?? '28px';

        return "<{$headingType} style=\"color: #333; font-size: {$size}; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #007bff;\">{$title}</{$headingType}>";
    }

    /**
     * Render table block for email
     */
    private function renderTableBlock(array $block): string
    {
        $hasHeader = $block['hasHeader'] ?? false;
        $rows = $block['rows'] ?? [];

        if (empty($rows)) {
            return '';
        }

        $html = [];
        $html[] = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';

        foreach ($rows as $index => $row) {
            $isHeader = $hasHeader && $index === 0;
            $html[] = '<tr>';

            foreach ($row as $cell) {
                $cellContent = htmlspecialchars($cell);

                if ($isHeader) {
                    $html[] = '<th style="background-color: #f8f9fa; color: #333; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">';
                    $html[] = $cellContent;
                    $html[] = '</th>';
                } else {
                    $html[] = '<td style="padding: 12px; border: 1px solid #ddd; color: #333;">';
                    $html[] = $cellContent;
                    $html[] = '</td>';
                }
            }

            $html[] = '</tr>';
        }

        $html[] = '</table>';

        return implode("\n", $html);
    }


    private function buildDigestTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">';
        $html[] = '<div style="background: white; padding: 30px; border-radius: 8px;">';

        // Header with date
        $html[] = '<h1 style="color: #333; margin-bottom: 10px;">' . htmlspecialchars($newsletter->title) . '</h1>';
        $html[] = '<p style="color: #666; font-size: 14px; margin-bottom: 30px;">' . date('F j, Y') . '</p>';

        // Summary
        $html[] = '<p style="color: #666; font-size: 16px; line-height: 1.6; margin-bottom: 30px;">';
        $html[] = 'Here are the ' . $pages->count() . ' latest articles from our site:';
        $html[] = '</p>';

        // Compact page list
        foreach ($pages as $page) {
            $html[] = $this->renderDigestItem($page);
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderDigestItem(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">';
        $html[] = '<h3 style="margin: 0 0 5px 0;">';
        $html[] = '<a href="' . $url . '" style="color: #007bff; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h3>';

        if ($page->meta_description) {
            $html[] = '<p style="color: #666; font-size: 14px; margin: 0;">';
            $html[] = htmlspecialchars(substr($page->meta_description, 0, 120)) . '...';
            $html[] = '</p>';
        }
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

    private function buildFeaturedTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        $html[] = '<div style="max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif;">';

        // First page as hero
        $featuredPage = $pages->first();
        if ($featuredPage) {
            $html[] = $this->renderHeroPage($featuredPage);
            $pages = $pages->slice(1);
        }

        // Rest as grid
        $html[] = '<div style="padding: 20px;">';
        $html[] = '<h2 style="color: #333; margin-bottom: 20px;">More Articles</h2>';

        foreach ($pages as $page) {
            $html[] = $this->renderCompactCard($page);
        }

        $html[] = '</div>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderHeroPage(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="position: relative; margin-bottom: 40px;">';

        if ($page->hero_image_id || $page->listing_image_id) {
            $imageId = $page->hero_image_id ?: $page->listing_image_id;
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($page->title) . '" style="width: 100%; height: 400px; object-fit: cover;">';
        }

        $html[] = '<div style="padding: 30px; background: white;">';
        $html[] = '<h1 style="margin: 0 0 15px 0; font-size: 32px;">';
        $html[] = '<a href="' . $url . '" style="color: #333; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h1>';

        if ($page->meta_description) {
            $html[] = '<p style="color: #666; font-size: 18px; line-height: 1.6; margin: 0 0 20px 0;">';
            $html[] = htmlspecialchars($page->meta_description);
            $html[] = '</p>';
        }

        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 16px;">Read Full Article</a>';
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderCompactCard(Page $page): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 4px;">';
        $html[] = '<h3 style="margin: 0 0 10px 0; font-size: 18px;">';
        $html[] = '<a href="' . $url . '" style="color: #333; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h3>';

        if ($page->meta_description) {
            $html[] = '<p style="color: #666; font-size: 14px; margin: 0;">';
            $html[] = htmlspecialchars(substr($page->meta_description, 0, 100)) . '...';
            $html[] = '</p>';
        }
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildSimpleTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken): string
    {
        $html = [];

        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">';
        $html[] = '<h2 style="color: #333;">' . htmlspecialchars($newsletter->title) . '</h2>';
        $html[] = '<ul style="list-style: none; padding: 0;">';

        foreach ($pages as $page) {
            $url = url($page->slug);
            $html[] = '<li style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">';
            $html[] = '<a href="' . $url . '" style="color: #007bff; text-decoration: none; font-weight: bold;">';
            $html[] = htmlspecialchars($page->title);
            $html[] = '</a>';

            if ($page->meta_description) {
                $html[] = '<p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">';
                $html[] = htmlspecialchars(substr($page->meta_description, 0, 150));
                $html[] = '</p>';
            }
            $html[] = '</li>';
        }

        $html[] = '</ul>';
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function buildDefaultTemplate(Newsletter $newsletter, Collection $pages, ?string $unsubscribeToken, bool $includeBlocks = false): string
    {
        $html = [];

        // Header
        $html[] = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">';
        $html[] = '<h1 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">' .
            htmlspecialchars($newsletter->title) . '</h1>';

        // Pages
        foreach ($pages as $page) {
            $html[] = $this->renderPageCard($page, $includeBlocks);
        }

        // Footer
        $html[] = $this->renderFooter($unsubscribeToken);
        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function renderPageCard(Page $page, bool $includeBlocks = true): string
    {
        $url = url($page->slug);
        $html = [];

        $html[] = '<div style="margin-bottom: 30px; padding: 20px; border: 1px solid #eee; border-radius: 8px;">';

        // Image if available
        if ($page->listing_image_id || $page->hero_image_id) {
            $imageId = $page->listing_image_id ?: $page->hero_image_id;
            $html[] = '<img src="' . url("/api/media/{$imageId}") . '" alt="' . htmlspecialchars($page->title) . '" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;">';
        }

        // Title
        $html[] = '<h2 style="margin: 0 0 10px 0;">';
        $html[] = '<a href="' . $url . '" style="color: #333; text-decoration: none;">';
        $html[] = htmlspecialchars($page->title);
        $html[] = '</a>';
        $html[] = '</h2>';

        // Meta
        $html[] = '<p style="color: #999; font-size: 14px; margin: 0 0 15px 0;">';
        if ($page->published_at) {
            $html[] = $page->published_at->format('F j, Y');
        }
        if ($page->authors && $page->authors->count() > 0) {
            $html[] = ' • By ' . htmlspecialchars($page->authors->first()->name);
        }
        $html[] = '</p>';

        // Description or blocks
        if ($includeBlocks) {
            $blocks = $page->blocks->toArray();

            if (is_array($blocks)) {
                // Render first few blocks
                $blockCount = 0;
                foreach ($blocks as $block) {
                    if ($blockCount >= 3) break; // Limit to first 3 blocks
                    $html[] = $this->renderBlockForEmail($block);
                    $blockCount++;
                }
            }
        } else {
            // Default: show description
            if ($page->meta_description || $page->listing_synopsis) {
                $description = $page->listing_synopsis ?: $page->meta_description;
                $html[] = '<p style="color: #666; line-height: 1.6; margin: 0 0 15px 0;">';
                $html[] = htmlspecialchars(substr($description, 0, 200));
                if (strlen($description) > 200) {
                    $html[] = '...';
                }
                $html[] = '</p>';
            }
        }

        // Read more button
        $html[] = '<a href="' . $url . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">Read More</a>';

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render person block for email
     */
    private function renderPersonBlock(array $block): string
    {
        $name = htmlspecialchars($block['name'] ?? '');
        $role = htmlspecialchars($block['role'] ?? '');
        $bio = htmlspecialchars($block['bio'] ?? '');
        $email = htmlspecialchars($block['email'] ?? '');
        $phone = htmlspecialchars($block['phone'] ?? '');
        $imageSrc = htmlspecialchars($block['image']['src'] ?? '');

        $html = [];
        $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; display: table; width: 100%;">';

        if (!empty($imageSrc)) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 100px; padding-right: 20px;">';
            $html[] = "<img src=\"{$imageSrc}\" alt=\"{$name}\" style=\"width: 100px; height: 100px; border-radius: 50%; object-fit: cover;\">";
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';
        $html[] = "<h3 style=\"color: #333; margin: 0 0 5px 0; font-size: 20px;\">{$name}</h3>";

        if (!empty($role)) {
            $html[] = "<p style=\"color: #666; margin: 0 0 10px 0; font-size: 14px; font-weight: bold;\">{$role}</p>";
        }

        if (!empty($bio)) {
            $html[] = "<p style=\"color: #333; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;\">{$bio}</p>";
        }

        if (!empty($email) || !empty($phone)) {
            $html[] = '<div style="margin-top: 15px;">';

            if (!empty($email)) {
                $html[] = '<a href="mailto:' . $email . '" style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; font-size: 14px;">Email</a>';
            }

            if (!empty($phone)) {
                $html[] = '<a href="tel:' . $phone . '" style="display: inline-block; padding: 8px 16px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Call</a>';
            }

            $html[] = '</div>';
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render product comparison block for email
     */
    private function renderProductComparisonBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $productA = htmlspecialchars($block['productA'] ?? '');
        $productB = htmlspecialchars($block['productB'] ?? '');
        $comparisons = $block['comparisons'] ?? [];

        $html = [];
        $html[] = '<div style="margin: 20px 0;">';
        $html[] = "<h3 style=\"color: #333; margin: 0 0 20px 0; font-size: 24px;\">{$title}</h3>";

        $html[] = '<table style="width: 100%; border-collapse: collapse;">';

        // Header row
        $html[] = '<tr>';
        $html[] = '<th style="background-color: #f8f9fa; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;"></th>';
        $html[] = "<th style=\"background-color: #e7f3ff; padding: 12px; text-align: center; border: 1px solid #ddd; font-weight: bold;\">{$productA}</th>";
        $html[] = "<th style=\"background-color: #fff3e7; padding: 12px; text-align: center; border: 1px solid #ddd; font-weight: bold;\">{$productB}</th>";
        $html[] = '</tr>';

        // Comparison rows
        foreach ($comparisons as $comparison) {
            $subtitle = htmlspecialchars($comparison['subtitle'] ?? '');
            $items = $comparison['items'] ?? [];

            $html[] = '<tr>';
            $html[] = "<td style=\"padding: 12px; border: 1px solid #ddd; font-weight: bold; color: #333;\">{$subtitle}</td>";

            foreach ($items as $item) {
                $value = htmlspecialchars($item['value'] ?? '');
                $html[] = "<td style=\"padding: 12px; border: 1px solid #ddd; text-align: center; color: #333;\">{$value}</td>";
            }

            $html[] = '</tr>';
        }

        $html[] = '</table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render schema block for email
     */
    private function renderSchemaBlock(array $block): string
    {
        $schemaType = $block['schemaType'] ?? 'how-to';

        if ($schemaType === 'question') {
            $question = htmlspecialchars($block['question'] ?? '');
            $answer = htmlspecialchars($block['answer'] ?? '');
            $expansion = htmlspecialchars($block['expansion'] ?? '');

            $html = [];
            $html[] = '<div style="background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 20px; margin: 20px 0; border-radius: 4px;">';
            $html[] = "<h3 style=\"color: #333; margin: 0 0 10px 0; font-size: 18px;\">{$question}</h3>";
            $html[] = "<p style=\"color: #333; margin: 0 0 10px 0; font-size: 16px; line-height: 1.6;\">{$answer}</p>";

            if (!empty($expansion)) {
                $html[] = "<p style=\"color: #666; margin: 0; font-size: 14px; line-height: 1.6;\">{$expansion}</p>";
            }

            $html[] = '</div>';

            return implode("\n", $html);
        } else {
            // how-to schema
            $title = htmlspecialchars($block['title'] ?? '');
            $description = htmlspecialchars($block['description'] ?? '');
            $imageSrc = htmlspecialchars($block['image']['src'] ?? '');

            $html = [];
            $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">';

            if (!empty($imageSrc)) {
                $html[] = "<img src=\"{$imageSrc}\" alt=\"{$title}\" style=\"max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;\">";
            }

            $html[] = "<h3 style=\"color: #333; margin: 0 0 10px 0; font-size: 20px;\">{$title}</h3>";

            if (!empty($description)) {
                $html[] = "<p style=\"color: #666; margin: 0; font-size: 16px; line-height: 1.6;\">{$description}</p>";
            }

            $html[] = '</div>';

            return implode("\n", $html);
        }
    }

    /**
     * Render stats block for email
     */
    private function renderStatsBlock(array $block): string
    {
        $title = htmlspecialchars($block['title'] ?? '');
        $stats = $block['stats'] ?? [];

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';

        if (!empty($title)) {
            $html[] = "<h3 style=\"color: #333; margin: 0 0 20px 0; font-size: 24px; text-align: center;\">{$title}</h3>";
        }

        $html[] = '<table style="width: 100%;">';
        $html[] = '<tr>';

        $statCount = count($stats);
        $cellWidth = $statCount > 0 ? floor(100 / $statCount) : 100;

        foreach ($stats as $stat) {
            $number = htmlspecialchars($stat['number'] ?? '');
            $label = htmlspecialchars($stat['label'] ?? '');
            $description = htmlspecialchars($stat['description'] ?? '');
            $icon = $stat['icon'] ?? '';

            $html[] = "<td style=\"width: {$cellWidth}%; text-align: center; padding: 20px; vertical-align: top;\">";

            if (!empty($icon)) {
                $html[] = "<div style=\"font-size: 32px; margin-bottom: 10px;\">{$icon}</div>";
            }

            $html[] = "<div style=\"color: #007bff; font-size: 36px; font-weight: bold; margin-bottom: 5px;\">{$number}</div>";
            $html[] = "<div style=\"color: #333; font-size: 16px; font-weight: bold; margin-bottom: 5px;\">{$label}</div>";

            if (!empty($description)) {
                $html[] = "<div style=\"color: #666; font-size: 14px;\">{$description}</div>";
            }

            $html[] = '</td>';
        }

        $html[] = '</tr>';
        $html[] = '</table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render testimonial block for email
     */
    private function renderTestimonialBlock(array $block): string
    {
        $testimonials = $block['testimonials'] ?? [];

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';
        $html[] = '<h3 style="color: #333; margin: 0 0 20px 0; font-size: 24px; text-align: center;">What Our Clients Say</h3>';

        foreach ($testimonials as $testimonial) {
            $text = htmlspecialchars($testimonial['text'] ?? '');
            $author = htmlspecialchars($testimonial['author'] ?? '');
            $role = htmlspecialchars($testimonial['role'] ?? '');
            $rating = (int)($testimonial['rating'] ?? 5);
            $imageSrc = htmlspecialchars($testimonial['image']['src'] ?? '');

            $html[] = '<div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;">';

            // Rating stars
            $stars = str_repeat('⭐', $rating);
            $html[] = "<div style=\"color: #ffc107; margin-bottom: 10px; font-size: 18px;\">{$stars}</div>";

            // Testimonial text
            $html[] = "<p style=\"color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0; font-style: italic;\">\"{$text}\"</p>";

            // Author info
            $html[] = '<div style="display: table; width: 100%;">';

            if (!empty($imageSrc)) {
                $html[] = '<div style="display: table-cell; vertical-align: middle; width: 50px; padding-right: 15px;">';
                $html[] = "<img src=\"{$imageSrc}\" alt=\"{$author}\" style=\"width: 50px; height: 50px; border-radius: 50%; object-fit: cover;\">";
                $html[] = '</div>';
            }

            $html[] = '<div style="display: table-cell; vertical-align: middle;">';
            $html[] = "<p style=\"margin: 0; color: #333; font-weight: bold; font-size: 16px;\">{$author}</p>";

            if (!empty($role)) {
                $html[] = "<p style=\"margin: 0; color: #666; font-size: 14px;\">{$role}</p>";
            }

            $html[] = '</div>';
            $html[] = '</div>';

            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }
}