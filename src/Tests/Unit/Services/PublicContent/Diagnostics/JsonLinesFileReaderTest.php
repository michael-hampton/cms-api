<?php

namespace App\Tests\Unit\Services\PublicContent\Diagnostics;

use App\Services\PublicContent\Diagnostics\JsonLinesFileReader;
use PHPUnit\Framework\TestCase;

final class JsonLinesFileReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir() . '/jsonl-reader-test-' . uniqid() . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function test_returns_empty_array_for_a_missing_file(): void
    {
        $reader = new JsonLinesFileReader();

        self::assertSame([], $reader->tail('/tmp/does-not-exist-' . uniqid() . '.jsonl', 10));
    }

    public function test_it_parses_json_lines_newest_first(): void
    {
        file_put_contents($this->path, implode("\n", [
                json_encode(['id' => 1]),
                json_encode(['id' => 2]),
                json_encode(['id' => 3]),
            ]) . "\n");

        $reader = new JsonLinesFileReader();
        $records = $reader->tail($this->path, 10);

        self::assertSame([3, 2, 1], array_column($records, 'id'));
    }

    public function test_it_limits_to_the_most_recent_records(): void
    {
        file_put_contents($this->path, implode("\n", [
                json_encode(['id' => 1]),
                json_encode(['id' => 2]),
                json_encode(['id' => 3]),
                json_encode(['id' => 4]),
            ]) . "\n");

        $reader = new JsonLinesFileReader();
        $records = $reader->tail($this->path, 2);

        self::assertSame([4, 3], array_column($records, 'id'));
    }

    public function test_it_skips_blank_lines_and_malformed_json(): void
    {
        file_put_contents($this->path, implode("\n", [
                json_encode(['id' => 1]),
                '',
                'not-json',
                json_encode(['id' => 2]),
            ]) . "\n");

        $reader = new JsonLinesFileReader();
        $records = $reader->tail($this->path, 10);

        self::assertSame([2, 1], array_column($records, 'id'));
    }
}