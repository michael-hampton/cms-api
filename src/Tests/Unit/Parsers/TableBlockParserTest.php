<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Parsers\TableBlockParser;
use PHPUnit\Framework\TestCase;

class TableBlockParserTest extends TestCase
{
    public function testTableParserGetType(): void
    {
        $parser = new TableBlockParser();
        $this->assertSame('table', $parser->getType());
    }

    public function testTableParserGetValidationRules(): void
    {
        $parser = new TableBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('hasHeader', $rules);
        $this->assertContainsOnlyInstancesOf(BooleanRule::class, array_filter($rules['hasHeader'], fn($r) => $r instanceof BooleanRule));

        $this->assertArrayHasKey('rows', $rules);
        $this->assertContainsOnlyInstancesOf(ArrayRule::class, array_filter($rules['rows'], fn($r) => $r instanceof ArrayRule));
    }

    public function testTableParserParse(): void
    {
        $parser = new TableBlockParser();
        $data = [
            'hasHeader' => true,
            'rows' => [
                ['Header A', 'Header B'],
                ['Value 1', 'Value 2'],
                ['Long Text 3', ''] // Empty cell in a row
            ]
        ];
        $parsed = $parser->parse($data);

        $this->assertTrue($parsed['hasHeader']);
        $this->assertCount(3, $parsed['rows']);
        $this->assertSame(2, $parsed['column_count']); // Max 2 columns
        $this->assertSame(6, $parsed['cell_count']); // 3 rows * 2 columns = 6 cells
        $this->assertSame(8, $parsed['total_word_count']); // 2+2+2 words
    }

    public function testTableParserParseEmptyAndInvalidRows(): void
    {
        $parser = new TableBlockParser();
        $data = [
            'rows' => [
                ['A', 'B'],
                [], // Empty row array (should be filtered out by parseRows, but it passes $parsedRow[] check if data is passed)
                'not an array', // Invalid row type (should be skipped)
                ['C']
            ]
        ];
        $parsed = $parser->parse($data);

        $this->assertCount(2, $parsed['rows']); // Only ['A', 'B'] and ['C'] remain
        $this->assertSame(2, $parsed['column_count']); // Max is 2
        $this->assertSame(3, $parsed['cell_count']); // 'A', 'B', 'C' = 3 cells
    }

    public function testTableParserGenerateHtml(): void
    {
        $parser = new TableBlockParser();
        $parsedData = [
            'hasHeader' => true,
            'rows' => [
                ['cells' => ['H1', 'H2'], 'is_header' => true],
                ['cells' => ['D1', 'D2'], 'is_header' => false],
                ['cells' => ['D3', 'D4'], 'is_header' => false],
            ]
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<th class="table-header-cell">H1</th>', $html);
        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringContainsString('<td class="table-cell">D1</td>', $html);
        $this->assertStringContainsString('<td class="table-cell">D4</td>', $html);

        // Test without header
        $parsedData['hasHeader'] = false;
        $htmlNoHeader = $parser->generateHtml($parsedData);
        $this->assertStringNotContainsString('<thead>', $htmlNoHeader);
        $this->assertStringContainsString('<tbody>', $htmlNoHeader); // All rows are data rows
        $this->assertStringContainsString('<td class="table-cell">H1</td>', $htmlNoHeader); // H1 is now in tbody
    }
}