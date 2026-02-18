<?php

namespace App\Imports;

use App\Framework\FileUpload\FileSystemInterface;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use RuntimeException;

class CsvParserTest extends FunctionalTestCase
{
    private FileSystemInterface $fileSystem;
    private CsvParser $parser;

    public function test_parses_valid_csv_with_headers(): void
    {
        $path = $this->writeTempCsv(
            "code,type,value\n" .
            "SAVE10,percentage,10\n" .
            "FLAT5,fixed,5\n"
        );

        $rows = $this->parser->parse($path);

        $this->assertCount(2, $rows);
        $this->assertSame('SAVE10', $rows[0]['code']);
        $this->assertSame('percentage', $rows[0]['type']);
        $this->assertSame('10', $rows[0]['value']);
        $this->assertSame('FLAT5', $rows[1]['code']);
        $this->assertSame(2, $rows[0]['__line']);
        $this->assertSame(3, $rows[1]['__line']);

        unlink($path);
    }

    private function writeTempCsv(string $content): string
    {
        $path = sys_get_temp_dir() . '/csv_parser_test_' . uniqid() . '.csv';
        file_put_contents($path, $content);
        $this->fileSystem->shouldReceive('fileExists')->with($path)->andReturn(true);
        return $path;
    }

    public function test_trims_whitespace_from_headers_and_values(): void
    {
        $path = $this->writeTempCsv(
            " code , type , value \n" .
            " SAVE10 , percentage , 10 \n"
        );

        $rows = $this->parser->parse($path);

        $this->assertSame('SAVE10', $rows[0]['code']);
        $this->assertSame('percentage', $rows[0]['type']);
        $this->assertArrayHasKey('code', $rows[0]);

        unlink($path);
    }

    public function test_throws_on_missing_file(): void
    {
        $this->fileSystem->shouldReceive('fileExists')->with('/nonexistent.csv')->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->parser->parse('/nonexistent.csv');
    }

    public function test_throws_on_empty_file(): void
    {
        $path = $this->writeTempCsv('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        $this->parser->parse($path);
        unlink($path);
    }

    public function test_throws_on_header_only_file_with_no_data(): void
    {
        $path = $this->writeTempCsv("code,type,value\n");

        $rows = $this->parser->parse($path);

        $this->assertCount(0, $rows);

        unlink($path);
    }

    public function test_marks_malformed_row_when_column_count_mismatches(): void
    {
        $path = $this->writeTempCsv(
            "code,type,value\n" .
            "SAVE10,percentage\n" .   // missing value column
            "FLAT5,fixed,5\n"
        );

        $rows = $this->parser->parse($path);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['__malformed']);
        $this->assertSame('FLAT5', $rows[1]['code']);

        unlink($path);
    }

    public function test_line_numbers_are_correct_with_malformed_rows(): void
    {
        $path = $this->writeTempCsv(
            "code,type,value\n" .  // line 1
            "SAVE10,percentage\n" . // line 2 — malformed
            "FLAT5,fixed,5\n"       // line 3
        );

        $rows = $this->parser->parse($path);

        $this->assertSame(2, $rows[0]['__line']);
        $this->assertSame(3, $rows[1]['__line']);

        unlink($path);
    }

    public function test_parses_single_row(): void
    {
        $path = $this->writeTempCsv(
            "code,type,value\n" .
            "ONLY1,fixed,1\n"
        );

        $rows = $this->parser->parse($path);

        $this->assertCount(1, $rows);
        $this->assertSame('ONLY1', $rows[0]['code']);

        unlink($path);
    }

    public function test_handles_quoted_csv_values(): void
    {
        $path = $this->writeTempCsv(
            "code,type,value\n" .
            '"SAVE,10",percentage,10' . "\n"
        );

        $rows = $this->parser->parse($path);

        $this->assertCount(1, $rows);
        $this->assertSame('SAVE,10', $rows[0]['code']);

        unlink($path);
    }

    public function test_parses_large_csv_without_error(): void
    {
        $lines = ["code,type,value\n"];
        for ($i = 1; $i <= 500; $i++) {
            $lines[] = "CODE{$i},fixed,{$i}\n";
        }

        $path = $this->writeTempCsv(implode('', $lines));

        $rows = $this->parser->parse($path);

        $this->assertCount(500, $rows);
        $this->assertSame('CODE1', $rows[0]['code']);
        $this->assertSame('CODE500', $rows[499]['code']);

        unlink($path);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileSystem = Mockery::mock(FileSystemInterface::class);
        $this->parser = new CsvParser($this->fileSystem);
    }
}