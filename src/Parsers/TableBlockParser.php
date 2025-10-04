<?php

// TableBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;

class TableBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'table';
    }

    public function getValidationRules(): array
    {
        return [
            'hasHeader' => [
                new BooleanRule()
            ],
            'rows' => [
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $rows = $this->parseRows($data['rows'] ?? []);
        $hasHeader = (bool)($data['hasHeader'] ?? false);

        return [
            'hasHeader' => $hasHeader,
            'rows' => $rows,
            'row_count' => count($rows),
            'column_count' => $this->getColumnCount($rows),
            'cell_count' => $this->getCellCount($rows),
            'total_word_count' => $this->calculateTotalWordCount($rows),
            'formatted_rows' => $this->formatRows($rows, $hasHeader)
        ];
    }

    private function parseRows(array $rows): array
    {
        $parsed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $parsedRow = [];
            foreach ($row as $cell) {
                $parsedRow[] = trim($cell);
            }

            if (!empty($parsedRow)) {
                $parsed[] = $parsedRow;
            }
        }

        return $parsed;
    }

    private function getColumnCount(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        return max(array_map('count', $rows));
    }

    private function getCellCount(array $rows): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += count($row);
        }

        return $total;
    }

    private function calculateTotalWordCount(array $rows): int
    {
        $totalWords = 0;

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $totalWords += str_word_count(strip_tags($cell));
            }
        }

        return $totalWords;
    }

    private function formatRows(array $rows, bool $hasHeader): array
    {
        $formatted = [];

        foreach ($rows as $index => $row) {
            $formattedRow = [
                'cells' => array_map('htmlspecialchars', $row),
                'is_header' => $hasHeader && $index === 0,
                'row_index' => $index
            ];

            $formatted[] = $formattedRow;
        }

        return $formatted;
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"table-block\">";
        $html .= "<table class=\"data-table\">";

        if ($parsedData['hasHeader'] && !empty($parsedData['formatted_rows'])) {
            $html .= "<thead>";
            $html .= "<tr class=\"table-header-row\">";

            foreach ($parsedData['formatted_rows'][0]['cells'] as $cell) {
                $html .= "<th class=\"table-header-cell\">{$cell}</th>";
            }

            $html .= "</tr>";
            $html .= "</thead>";
        }

        $html .= "<tbody>";

        $startIndex = $parsedData['hasHeader'] ? 1 : 0;
        for ($i = $startIndex; $i < count($parsedData['formatted_rows']); $i++) {
            $row = $parsedData['formatted_rows'][$i];
            $html .= "<tr class=\"table-row\">";

            foreach ($row['cells'] as $cell) {
                $html .= "<td class=\"table-cell\">{$cell}</td>";
            }

            $html .= "</tr>";
        }

        $html .= "</tbody>";
        $html .= "</table>";
        $html .= "</div>";

        return $html;
    }
}