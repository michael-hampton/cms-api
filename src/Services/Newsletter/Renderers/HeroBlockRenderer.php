<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\HeroBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class HeroBlockRenderer implements EmailBlockRenderer
{
    public $type = 'hero';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof HeroBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];

        $backgroundStyle = '';
        if ($blockData->backgroundImage) {
            $backgroundStyle = "background-image: url('" . Str::sanitize($blockData->backgroundImage) . "'); background-size: cover; background-position: center;";
        } else {
            $backgroundStyle = 'background: ' . $this->themeGradient($blockData->theme) . ';';
        }

        $html[] = '<div style="' . $backgroundStyle . ' color: white; padding: 40px 30px; border-radius: 8px; text-align: center; margin: 20px 0; position: relative;">';

        // Overlay for better text readability on background images
        if ($blockData->backgroundImage) {
            $html[] = '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); border-radius: 8px;"></div>';
            $html[] = '<div style="position: relative; z-index: 1;">';
        }

        $html[] = '<h1 style="color: white; margin: 0 0 15px 0; font-size: 32px; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">' . Str::sanitize($blockData->title) . '</h1>';

        $subtitle = $blockData->subtitle ?? $blockData->subheading;
        if ($subtitle) {
            $html[] = '<p style="color: white; margin: 0 0 20px 0; font-size: 18px; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">' . Str::sanitize($subtitle) . '</p>';
        }

        $html[] = '<div style="margin-top: 20px;">';

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 12px 30px; background-color: white; color: #667eea; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; margin-right: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        if ($blockData->secondaryCtaText && $blockData->secondaryCtaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->secondaryCtaUrl) . '" style="display: inline-block; padding: 12px 30px; background-color: transparent; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; border: 2px solid white;">';
            $html[] = Str::sanitize($blockData->secondaryCtaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';

        if ($blockData->backgroundImage) {
            $html[] = '</div>'; // Close relative z-index wrapper
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }

    private function themeGradient(?string $theme): string
    {
        return match ($theme) {
            'Light Green' => 'linear-gradient(135deg, #9fd9b5 0%, #4d9c74 100%)',
            'Light Blue' => 'linear-gradient(135deg, #9bd0ff 0%, #3f7fbd 100%)',
            'White' => 'linear-gradient(135deg, #f3f3f3 0%, #cfcfcf 100%)',
            'Green' => 'linear-gradient(135deg, #3d8b63 0%, #1f5a3c 100%)',
            'Blue' => 'linear-gradient(135deg, #4f8fd9 0%, #1d4f91 100%)',
            'Purple' => 'linear-gradient(135deg, #7a68c7 0%, #4a357f 100%)',
            default => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        };
    }
}
