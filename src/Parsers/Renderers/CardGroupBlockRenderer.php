<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\CardGroupBlockDto;

class CardGroupBlockRenderer extends BaseBlockRenderer
{
    private CardBlockRenderer $cardRenderer;

    public function __construct()
    {
        $this->cardRenderer = new CardBlockRenderer();
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof CardGroupBlockDto) {
            throw new \InvalidArgumentException('Expected CardGroupBlockDto');
        }

        if (empty($dto->cards)) {
            return '';
        }

        $containerClass = "card-group-block card-group-items-{$dto->itemsPerRow} card-group-gap-{$dto->gap}";

        $html = "<div class=\"{$containerClass}\">";
        $html .= "<div class=\"card-group-container\">";

        foreach ($dto->cards as $card) {
            $html .= "<div class=\"card-group-item\">";
            $html .= $this->cardRenderer->render($card);
            $html .= "</div>";
        }

        $html .= "</div>"; // card-group-container
        $html .= "</div>"; // card-group-block

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'card-group';
    }
}