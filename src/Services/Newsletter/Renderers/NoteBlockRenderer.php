<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\NoteBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class NoteBlockRenderer implements EmailBlockRenderer
{
    public $type = 'note';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof NoteBlockData) {
            return RenderedBlock::skipped();
        }

        $alignmentStyle = match ($blockData->alignment) {
            'left' => 'margin-right: auto; width: 75%;',
            'center' => 'margin-left: auto; margin-right: auto; width: 75%;',
            'right' => 'margin-left: auto; width: 75%;',
            default => 'width: 100%;',
        };

        $baseStyle = 'background-color: #f8f9fa; border-left: 4px solid #007bff; border-radius: 4px; padding: 20px; margin: 20px 0; ' . $alignmentStyle;
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);

        $baseTitleStyle = 'color: #333; margin: 0 0 15px 0; font-size: 20px;';
        $titleStyle = $blockData->style->mergeIntoCss($baseTitleStyle);

        $baseParaStyle = 'color: #333; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;';
        $paraStyle = $blockData->style->mergeIntoCss($baseParaStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title) . '" style="max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;">';
        }

        $html[] = "<h3 style=\"{$titleStyle}\">" . Str::sanitize($blockData->title) . '</h3>';

        foreach ($blockData->paragraphs as $paragraph) {
            $html[] = "<p style=\"{$paraStyle}\">" . Str::sanitize($paragraph) . '</p>';
        }

        if ($blockData->linkUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->linkUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">'
                . Str::sanitize($blockData->linkText)
                . ($blockData->sponsored ? ' <span style="background-color: #ffc107; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Sponsored</span>' : '')
                . '</a>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}
