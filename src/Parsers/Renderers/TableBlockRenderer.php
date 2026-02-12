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

        $html = '<div class="table-block">';
        $html .= '<table class="data-table">';

        $rows = $dto->rows;
        $theadRows = [];
        $tbodyRows = [];

        // Split rows into <thead> and <tbody> based on hasHeader
        if ($dto->hasHeader && !empty($rows)) {
            $firstRow = array_shift($rows); // remove first row
            // Handle backwards-compatible row (numeric array)
            if (array_keys($firstRow) === range(0, count($firstRow) - 1)) {
                $firstRow = ['cells' => $firstRow, 'is_header' => true];
            }
            $theadRows[] = $firstRow['cells'];
        }

        foreach ($rows as $row) {
            // Backwards-compatible: old numeric-array row
            if (array_keys($row) === range(0, count($row) - 1)) {
                $row = ['cells' => $row, 'is_header' => false];
            }
            $tbodyRows[] = $row['cells'];
        }

        // Render <thead>
        if (!empty($theadRows)) {
            $html .= '<thead>';
            foreach ($theadRows as $row) {
                $html .= '<tr class="table-header-row">';
                foreach ($row as $cell) {
                    $html .= '<th class="table-header-cell">' . $this->escape($cell) . '</th>';
                }
                $html .= '</tr>';
            }
            $html .= '</thead>';
        }

        // Render <tbody>
        $html .= '<tbody>';
        foreach ($tbodyRows as $row) {
            $html .= '<tr class="table-row">';
            foreach ($row as $cell) {
                $html .= '<td class="table-cell">' . $this->escape($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

}