<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\TeamBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class TeamBlockRenderer implements EmailBlockRenderer
{
    public $type = 'team';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof TeamBlockData) {
            return RenderedBlock::skipped();
        }

        $baseStyle = 'margin: 30px 0;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if ($blockData->title) {
            $baseTitleStyle = 'color: #333; margin: 0 0 10px 0; font-size: 24px; text-align: center;';
            $titleStyle = $blockData->style->mergeIntoCss($baseTitleStyle);
            $html[] = "<h3 style=\"{$titleStyle}\">" . Str::sanitize($blockData->title) . '</h3>';
        }
        if ($blockData->subtitle) {
            $html[] = '<p style="color: #666; margin: 0 0 30px 0; font-size: 16px; text-align: center;">' . Str::sanitize($blockData->subtitle) . '</p>';
        }

        $html[] = '<table style="width: 100%;"><tr>';

        $count = count($blockData->members);
        $columns = min($count, 3);
        $cellWidth = $columns > 0 ? floor(100 / $columns) : 100;

        foreach ($blockData->members as $index => $member) {
            if ($index > 0 && $index % $columns === 0) {
                $html[] = '</tr><tr>';
            }

            $html[] = "<td style=\"width: {$cellWidth}%; padding: 20px; vertical-align: top; text-align: center;\">";

            if (isset($member['image']['src'])) {
                $html[] = '<img src="' . Str::sanitize($member['image']['src']) . '" alt="' . Str::sanitize($member['name']) . '" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 15px;">';
            }

            $html[] = '<h4 style="margin: 0 0 5px 0; font-size: 18px; color: #333;">' . Str::sanitize($member['name']) . '</h4>';

            if (!empty($member['role'])) {
                $html[] = '<p style="margin: 0 0 10px 0; font-size: 14px; color: #666; font-weight: bold;">' . Str::sanitize($member['role']) . '</p>';
            }

            if (!empty($member['bio'])) {
                $baseBioStyle = 'margin: 0 0 15px 0; font-size: 14px; color: #666; line-height: 1.5;';
                $bioStyle = $blockData->style->mergeIntoCss($baseBioStyle);
                $truncated = mb_strlen($member['bio']) > 100 ? mb_substr($member['bio'], 0, 100) . '…' : $member['bio'];
                $html[] = "<p style=\"{$bioStyle}\">" . Str::sanitize($truncated) . '</p>';
            }

            if (!empty($member['email']) || !empty($member['phone'])) {
                $html[] = '<div style="margin-top: 10px;">';
                if (!empty($member['email'])) {
                    $html[] = '<a href="mailto:' . Str::sanitize($member['email']) . '" style="display: inline-block; padding: 6px 12px; background-color: #007bff; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; margin: 2px;">Email</a>';
                }
                if (!empty($member['phone'])) {
                    $html[] = '<a href="tel:' . Str::sanitize($member['phone']) . '" style="display: inline-block; padding: 6px 12px; background-color: #28a745; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; margin: 2px;">Call</a>';
                }
                $html[] = '</div>';
            }

            $html[] = '</td>';
        }

        $html[] = '</tr></table></div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}