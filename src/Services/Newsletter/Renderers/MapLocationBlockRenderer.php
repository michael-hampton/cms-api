<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\MapLocationBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

// ... imports

class MapLocationBlockRenderer implements EmailBlockRenderer
{
    public $type = 'map-location';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof MapLocationBlockData) return RenderedBlock::skipped();

        $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($blockData->address);

        $html = [];
        $html[] = '<div style="text-align: center; border: 1px dashed #ccc; padding: 20px; margin: 20px 0; border-radius: 8px;">';

        if ($blockData->title) {
            $html[] = '<h3 style="margin-top: 0;">' . Str::sanitize($blockData->title) . '</h3>';
        }

        $html[] = '<p style="color: #666; font-size: 14px;">' . Str::sanitize($blockData->address) . '</p>';

        // Newsletter adaptation: Replace iframe with a button linking to Google Maps
        $html[] = '<a href="' . $mapUrl . '" style="display: inline-block; margin-top: 10px; color: #007bff; font-weight: bold; text-decoration: underline;">View on Google Maps</a>';

        if ($blockData->description) {
            $html[] = '<p style="font-size: 12px; color: #888; margin-top: 15px; font-style: italic;">' . Str::sanitize($blockData->description) . '</p>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}