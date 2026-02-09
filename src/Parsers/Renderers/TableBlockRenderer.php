<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\TableBlockDto;

class TableBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'table';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof TableBlockDto) {
            return '';
        }

        $html = "<div class=\"table-block\">";
        $html .= "<table class=\"data-table\">";

        $startIndex = 0;

        if ($dto->hasHeader && !empty($dto->rows)) {
            $html .= "<thead>";
            $html .= "<tr class=\"table-header-row\">";

            foreach ($dto->rows[0] as $cell) {
                $html .= "<th class=\"table-header-cell\">{$this->escape($cell)}</th>";
            }

            $html .= "</tr>";
            $html .= "</thead>";
            $startIndex = 1;
        }

        $html .= "<tbody>";

        for ($i = $startIndex; $i < count($dto->rows); $i++) {
            $html .= "<tr class=\"table-row\">";

            foreach ($dto->rows[$i] as $cell) {
                $html .= "<td class=\"table-cell\">{$this->escape($cell)}</td>";
            }

            $html .= "</tr>";
        }

        $html .= "</tbody>";
        $html .= "</table>";
        $html .= "</div>";

        return $html;
    }
}