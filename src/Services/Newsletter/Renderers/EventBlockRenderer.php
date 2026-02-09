<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\EventBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

// ... imports

class EventBlockRenderer implements EmailBlockRenderer
{
    public $type = 'event';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof EventBlockData) return RenderedBlock::skipped();

        $html = [];
        $html[] = '<div style="background-color: #f9f9f9; border-left: 4px solid #007bff; padding: 20px; margin: 20px 0;">';

        $html[] = '<h2 style="margin: 0 0 10px 0; color: #333;">' . Str::sanitize($blockData->title) . '</h2>';
        $html[] = '<p style="color: #007bff; font-weight: bold; margin-bottom: 5px;">📅 ' . $blockData->formattedDate . '</p>';

        if ($blockData->location) {
            $html[] = '<p style="color: #666; margin-bottom: 15px;">📍 ' . Str::sanitize($blockData->location) . '</p>';
        }

        $html[] = '<p style="font-size: 14px; line-height: 1.6; color: #444;">' . nl2br(Str::sanitize($blockData->description)) . '</p>';

        if ($blockData->ticketUrl) {
            $priceText = $blockData->ticketPrice > 0 ? $blockData->currency . $blockData->ticketPrice : 'Free';
            $html[] = '<div style="margin-top: 20px;">';
            $html[] = '<a href="' . Str::sanitize($blockData->ticketUrl) . '" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">Get Tickets (' . $priceText . ')</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';
        return RenderedBlock::rendered(implode("\n", $html));
    }
}