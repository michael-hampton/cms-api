<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\QuoteBlockDto;

class QuoteBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'quote';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof QuoteBlockDto) {
            return '';
        }

        $contextClass = $dto->context === 'sidebar' ? ' quote-sidebar' : '';
        $html = "<blockquote class=\"quote-block{$contextClass}\">";

        $html .= "<div class=\"quote-text\">{$this->escapeWithBreaks($dto->text)}</div>";

        if (!empty($dto->attribution)) {
            $html .= "<cite class=\"quote-attribution\">{$this->escape($dto->attribution)}</cite>";
        }

        $html .= "</blockquote>";

        return $html;
    }
}