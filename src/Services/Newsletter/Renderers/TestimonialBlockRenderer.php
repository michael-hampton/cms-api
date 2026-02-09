<?php

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

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof TestimonialBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';
        $html[] = '<h3 style="color: #333; margin: 0 0 20px 0; font-size: 24px; text-align: center;">What Our Clients Say</h3>';

        foreach ($blockData->testimonials as $testimonial) {
            $text = Str::sanitize($testimonial['text'] ?? '');
            $author = Str::sanitize($testimonial['author'] ?? '');
            $role = Str::sanitize($testimonial['role'] ?? '');
            $rating = (int)($testimonial['rating'] ?? 5);
            $imageSrc = isset($testimonial['image']['src']) ? Str::sanitize($testimonial['image']['src']) : null;

            $html[] = '<div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;">';

            // Rating stars
            $stars = Rating::generateStars($rating);
            $html[] = "<div style=\"color: #ffc107; margin-bottom: 10px; font-size: 18px;\">{$stars}</div>";

            // Testimonial text
            $html[] = "<p style=\"color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0; font-style: italic;\">\"{$text}\"</p>";

            // Author info
            $html[] = '<div style="display: table; width: 100%;">';

            if ($imageSrc) {
                $html[] = '<div style="display: table-cell; vertical-align: middle; width: 50px; padding-right: 15px;">';
                $html[] = "<img src=\"{$imageSrc}\" alt=\"{$author}\" style=\"width: 50px; height: 50px; border-radius: 50%; object-fit: cover;\">";
                $html[] = '</div>';
            }

            $html[] = '<div style="display: table-cell; vertical-align: middle;">';
            $html[] = "<p style=\"margin: 0; color: #333; font-weight: bold; font-size: 16px;\">{$author}</p>";

            if ($role) {
                $html[] = "<p style=\"margin: 0; color: #666; font-size: 14px;\">{$role}</p>";
            }

            $html[] = '</div>';
            $html[] = '</div>';

            $html[] = '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}