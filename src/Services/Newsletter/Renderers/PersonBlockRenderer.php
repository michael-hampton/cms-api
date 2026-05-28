<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\PersonBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class PersonBlockRenderer implements EmailBlockRenderer
{
    public $type = 'person';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof PersonBlockData) {
            return RenderedBlock::skipped();
        }

        $baseStyle = 'border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; display: table; width: 100%;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);

        $baseNameStyle = 'color: #333; margin: 0 0 5px 0; font-size: 20px;';
        $nameStyle = $blockData->style->mergeIntoCss($baseNameStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 100px; padding-right: 20px;">'
                . '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->name) . '" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">'
                . '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';
        $html[] = "<h3 style=\"{$nameStyle}\">" . Str::sanitize($blockData->name) . '</h3>';

        if ($blockData->role) {
            $html[] = '<p style="color: #666; margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">' . Str::sanitize($blockData->role) . '</p>';
        }

        if ($blockData->strapline) {
            $html[] = '<p style="color: #888; margin: 0 0 10px 0; font-size: 13px; font-style: italic;">' . Str::sanitize($blockData->strapline) . '</p>';
        }

        if ($blockData->bio) {
            $baseBioStyle = 'color: #333; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;';
            $bioStyle = $blockData->style->mergeIntoCss($baseBioStyle);
            $html[] = "<p style=\"{$bioStyle}\">" . Str::sanitize($blockData->bio) . '</p>';
        }

        if ($blockData->email || $blockData->phone) {
            $html[] = '<div style="margin-top: 15px;">';
            if ($blockData->email) {
                $html[] = '<a href="mailto:' . Str::sanitize($blockData->email) . '" style="display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; font-size: 14px;">Email</a>';
            }
            if ($blockData->phone) {
                $html[] = '<a href="tel:' . Str::sanitize($blockData->phone) . '" style="display: inline-block; padding: 8px 16px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Call</a>';
            }
            $html[] = '</div>';
        }

        $socialLinks = array_filter([
            'Twitter' => $blockData->twitter,
            'LinkedIn' => $blockData->linkedin,
            'Instagram' => $blockData->instagram,
            'Facebook' => $blockData->facebook,
            'TikTok' => $blockData->tiktok,
            'YouTube' => $blockData->youtube,
        ]);

        if (!empty($socialLinks)) {
            $links = [];
            foreach ($socialLinks as $label => $url) {
                $links[] = '<a href="' . Str::sanitize((string)$url) . '" style="color: #007bff; text-decoration: none; margin-right: 10px;">' . Str::sanitize($label) . '</a>';
            }
            $html[] = '<p style="margin: 12px 0 0 0; font-size: 13px;">' . implode('', $links) . '</p>';
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}
