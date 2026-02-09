<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Rating;
use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\AwardBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class AwardBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'award';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof AwardBlockData) {
            return RenderedBlock::skipped();
        }

        $borderColor = $blockData->winner ? '#FFD700' : '#ddd';
        $backgroundColor = $blockData->winner ? '#fffef0' : '#ffffff';

        $html = [];
        $html[] = "<div style=\"border: 2px solid {$borderColor}; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: {$backgroundColor};\">";

        if ($blockData->winner) {
            $html[] = '<div style="background-color: #FFD700; color: #333; padding: 8px 16px; border-radius: 4px; display: inline-block; font-weight: bold; margin-bottom: 15px;">🏆 Winner</div>';
        }

        $html[] = '<div style="display: table; width: 100%;">';

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 150px; padding-right: 20px;">';
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->productName) . '" style="width: 150px; height: auto; border-radius: 4px;">';
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';
        $html[] = '<div style="color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">' . Str::sanitize($blockData->subcategory) . '</div>';
        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 20px;">' . Str::sanitize($blockData->productName) . '</h3>';

        if ($blockData->strapline) {
            $html[] = '<p style="color: #666; margin: 0 0 10px 0; font-size: 14px; font-style: italic;">' . Str::sanitize($blockData->strapline) . '</p>';
        }

        if ($blockData->rating > 0) {
            $stars = Rating::generateStars($blockData->rating);
            $clamped = Rating::clampRating($blockData->rating);
            $html[] = "<div style=\"color: #ffc107; margin-bottom: 10px;\">{$stars} {$clamped}/5</div>";
        }

        if ($blockData->caption) {
            $html[] = '<p style="color: #333; margin: 0; font-size: 14px; line-height: 1.6;">' . Str::sanitize($blockData->caption) . '</p>';
        }

        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}