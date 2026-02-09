<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\CardBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class CardBlockRenderer implements EmailBlockRenderer
{
    public $type = 'card';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof CardBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin: 20px 0; background: white;">';

        if ($blockData->sponsorDeclaration) {
            $decl = $blockData->sponsorDeclaration;
            $html[] = '<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">';

            if (isset($decl['sponsorLogo']['src'])) {
                $html[] = '<img src="' . Str::sanitize($decl['sponsorLogo']['src']) . '" alt="' . Str::sanitize($decl['sponsorName'] ?? 'Sponsor') . '" style="max-width: 100px; height: auto; margin-bottom: 5px;">';
            }

            if (!empty($decl['sponsoredText'])) {
                $html[] = '<div style="color: #666; font-size: 12px;">' . Str::sanitize($decl['sponsoredText']) . '</div>';
            }

            if (!empty($decl['sponsorName'])) {
                $html[] = '<div style="color: #333; font-size: 14px; font-weight: bold;">' . Str::sanitize($decl['sponsorName']) . '</div>';
            }

            $html[] = '</div>';
        }

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<div style="position: relative;">';

            if ($blockData->linkUrl) {
                $html[] = '<a href="' . Str::sanitize($blockData->linkUrl) . '">';
            }

            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title) . '" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;">';

            if ($blockData->endorsement && isset($blockData->endorsement['src'])) {
                $html[] = '<img src="' . Str::sanitize($blockData->endorsement['src']) . '" alt="Endorsement" style="position: absolute; top: 10px; right: 10px; max-width: 80px; height: auto;">';
            }

            if ($blockData->linkUrl) {
                $html[] = '</a>';
            }

            $html[] = '</div>';
        }

        $html[] = '<h3 style="margin: 0 0 10px 0; font-size: 20px; color: #333;">' . Str::sanitize($blockData->title) . '</h3>';

        if ($blockData->description) {
            $html[] = '<p style="margin: 0 0 15px 0; font-size: 14px; color: #666; line-height: 1.6;">' . Str::sanitize($blockData->description) . '</p>';
        }

        if ($blockData->linkUrl) {
            $bgColor = match ($blockData->buttonType) {
                'secondary' => '#6c757d',
                'text' => 'transparent',
                default => '#007bff'
            };

            $textColor = $blockData->buttonType === 'text' ? '#007bff' : 'white';
            $border = $blockData->buttonType === 'text' ? 'border: none;' : '';

            $linkAttrs = '';
            if ($blockData->noFollow) $linkAttrs .= ' rel="nofollow"';
            if ($blockData->sponsored) $linkAttrs .= ' rel="sponsored"';
            if ($blockData->openInNewTab) $linkAttrs .= ' target="_blank"';

            $html[] = '<a href="' . Str::sanitize($blockData->linkUrl) . '"' . $linkAttrs . ' style="display: inline-block; padding: 10px 20px; background-color: ' . $bgColor . '; color: ' . $textColor . '; text-decoration: none; border-radius: 4px; font-weight: bold; ' . $border . '">';
            $html[] = Str::sanitize($blockData->buttonText);
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}