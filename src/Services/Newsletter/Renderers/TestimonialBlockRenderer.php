<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Rating;
use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\TestimonialBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class TestimonialBlockRenderer implements EmailBlockRenderer
{
    public $type = 'testimonial';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof TestimonialBlockData) {
            return RenderedBlock::skipped();
        }

        if (empty($blockData->testimonials)) {
            return RenderedBlock::skipped();
        }

        $wrapperStyle = $blockData->style->mergeIntoCss('margin: 30px 0;');
        $cardStyle = $blockData->style->mergeIntoCss(
            'background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;'
        );
        $textStyle = 'color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0; font-style: italic;';

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if ($blockData->title) {
            $title = Str::sanitize($blockData->title);
            $html[] = "<h3 style=\"color: #333; margin: 0 0 20px 0; font-size: 24px; text-align: center;\">{$title}</h3>";
        }

        if ($blockData->subtitle) {
            $subtitle = Str::sanitize($blockData->subtitle);
            $html[] = "<p style=\"color: #666; text-align: center; margin: 0 0 20px 0;\">{$subtitle}</p>";
        }

        foreach ($blockData->testimonials as $testimonial) {
            // Frontend uses strapline/productName/subcategory; seeders/legacy use text/author/role
            $text = Str::sanitize($testimonial['strapline'] ?? $testimonial['text'] ?? '');
            $author = Str::sanitize($testimonial['productName'] ?? $testimonial['author'] ?? '');
            $role = Str::sanitize($testimonial['subcategory'] ?? $testimonial['role'] ?? '');
            $rating = (int)($testimonial['rating'] ?? 0);
            $isWinner = (bool)($testimonial['winner'] ?? false);

            $stars = $rating > 0 ? Rating::generateStars($rating) : '';

            $html[] = "<div style=\"{$cardStyle}\">";

            if ($isWinner) {
                $html[] = '<div style="color: #e6a817; font-weight: bold; margin-bottom: 8px;">🏆 Winner</div>';
            }

            if ($stars) {
                $html[] = "<div style=\"color: #ffc107; margin-bottom: 10px; font-size: 18px;\">{$stars}</div>";
            }

            if ($text) {
                $html[] = "<p style=\"{$textStyle}\">\"{$text}\"</p>";
            }

            $html[] = '<div style="display: table; width: 100%;">';
            $html[] = '<div style="display: table-cell; vertical-align: middle;">';
            $html[] = "<p style=\"margin: 0; color: #333; font-weight: bold; font-size: 16px;\">{$author}</p>";

            if ($role) {
                $html[] = "<p style=\"margin: 0; color: #666; font-size: 14px;\">{$role}</p>";
            }

            $html[] = '</div></div></div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}