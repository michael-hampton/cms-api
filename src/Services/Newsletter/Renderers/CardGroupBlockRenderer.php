<?php

namespace App\Services\Newsletter\Renderers;

use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\CardBlockData;
use App\Services\Newsletter\DTOs\BlockData\CardGroupBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class CardGroupBlockRenderer implements EmailBlockRenderer
{
    public $type = 'card-group';

    public function __construct(
        private readonly CardBlockRenderer $cardRenderer
    )
    {
    }

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof CardGroupBlockData) {
            return RenderedBlock::skipped();
        }

        if (empty($blockData->cards)) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';
        $html[] = '<table style="width: 100%;"><tr>';

        $cellWidth = floor(100 / $blockData->itemsPerRow);
        $gapPadding = match ($blockData->gap) {
            'small' => '10px',
            'large' => '20px',
            default => '15px'
        };

        foreach ($blockData->cards as $index => $card) {
            if ($index > 0 && $index % $blockData->itemsPerRow === 0) {
                $html[] = '</tr><tr>';
            }

            $html[] = "<td style=\"width: {$cellWidth}%; padding: {$gapPadding}; vertical-align: top;\">";

            // Render individual card
            $cardData = CardBlockData::fromArray($card);
            $rendered = $this->cardRenderer->render($cardData, $newsletterRenderContext);

            if ($rendered->wasRendered) {
                $html[] = $rendered->html;
            }

            $html[] = '</td>';
        }

        $html[] = '</tr></table>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}