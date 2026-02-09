<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\TextBlockDto;

class TextBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'text';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof TextBlockDto) {
            return '';
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(TextBlockDto $dto): string
    {
        $html = "<div class=\"text-block text-block-sidebar\">";

        foreach ($dto->paragraphs as $paragraph) {
            $html .= "<p class=\"sidebar-text\">{$this->escape($paragraph)}</p>";
        }

        $html .= "</div>";
        return $html;
    }

    private function renderDefault(TextBlockDto $dto): string
    {
        $html = "<div class=\"text-block\">";

        foreach ($dto->paragraphs as $paragraph) {
            $html .= "<p class=\"text-paragraph\">{$this->escape($paragraph)}</p>";
        }

        $html .= "</div>";
        return $html;
    }
}