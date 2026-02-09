<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ServicesBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ServicesBlockRenderer implements EmailBlockRenderer
{
    public $type = 'services';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof ServicesBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';

        if ($blockData->title) {
            $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 24px; text-align: center;">' . Str::sanitize($blockData->title) . '</h3>';
        }

        if ($blockData->subtitle) {
            $html[] = '<p style="color: #666; margin: 0 0 30px 0; font-size: 16px; text-align: center;">' . Str::sanitize($blockData->subtitle) . '</p>';
        }

        $html[] = '<table style="width: 100%;"><tr>';

        $serviceCount = count($blockData->services);
        $columns = min($serviceCount, 3);
        $cellWidth = floor(100 / $columns);

        foreach ($blockData->services as $index => $service) {
            if ($index > 0 && $index % $columns === 0) {
                $html[] = '</tr><tr>';
            }

            $html[] = "<td style=\"width: {$cellWidth}%; padding: 20px; vertical-align: top; text-align: center;\">";

            if (isset($service['image']['src'])) {
                $html[] = '<img src="' . Str::sanitize($service['image']['src']) . '" alt="' . Str::sanitize($service['title']) . '" style="width: 100%; max-width: 200px; height: auto; border-radius: 8px; margin-bottom: 15px;">';
            } elseif (!empty($service['icon'])) {
                $html[] = '<div style="font-size: 48px; margin-bottom: 15px;">' . $service['icon'] . '</div>';
            }

            $html[] = '<h4 style="margin: 0 0 10px 0; font-size: 18px; color: #333;">' . Str::sanitize($service['title']) . '</h4>';

            if (!empty($service['description'])) {
                $html[] = '<p style="margin: 0 0 15px 0; font-size: 14px; color: #666; line-height: 1.5;">' . Str::sanitize($service['description']) . '</p>';
            }

            if (!empty($service['url']) && $service['url'] !== '#') {
                $html[] = '<a href="' . Str::sanitize($service['url']) . '" style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Learn More</a>';
            }

            $html[] = '</td>';
        }

        $html[] = '</tr></table>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}