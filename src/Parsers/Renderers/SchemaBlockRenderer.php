<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\SchemaBlockDto;

class SchemaBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'schema';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof SchemaBlockDto) {
            return '';
        }

        if ($dto->schemaType === 'question') {
            return $this->renderQuestion($dto);
        }

        return $this->renderHowTo($dto);
    }

    private function renderQuestion(SchemaBlockDto $dto): string
    {
        $html = "<div class=\"schema-block schema-type-question\">";
        $html .= "<div class=\"schema-question-block\">";
        $html .= "<h3 class=\"schema-question\">{$this->escape($dto->question)}</h3>";
        $html .= "<div class=\"schema-answer\">{$this->escapeWithBreaks($dto->answer)}</div>";

        if ($dto->showExpansion && !empty($dto->expansion)) {
            $html .= "<div class=\"schema-expansion\">{$this->escapeWithBreaks($dto->expansion)}</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function renderHowTo(SchemaBlockDto $dto): string
    {
        $html = "<div class=\"schema-block schema-type-how-to\">";
        $html .= "<div class=\"schema-howto-block\">";

        if (!empty($dto->image) && !empty($dto->image['src'])) {
            $html .= "<img src=\"{$this->escape($dto->image['src'])}\" alt=\"{$this->escape($dto->title)}\" class=\"schema-image\">";
        }

        $html .= "<h3 class=\"schema-title\">{$this->escape($dto->title)}</h3>";

        if (!empty($dto->description)) {
            $html .= "<div class=\"schema-description\">{$this->escapeWithBreaks($dto->description)}</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}