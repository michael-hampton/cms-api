<?php

namespace App\Tests\Unit\Services\PublicContent\Parity;

use App\Services\PublicContent\Parity\PublicContentParityReportWriter;
use PHPUnit\Framework\TestCase;

final class PublicContentParityReportWriterTest extends TestCase
{
    private ?string $overridePath = null;

    protected function tearDown(): void
    {
        putenv('PUBLIC_CONTENT_PARITY_REPORT_PATH');
        unset($_ENV['PUBLIC_CONTENT_PARITY_REPORT_PATH']);

        if ($this->overridePath !== null && is_file($this->overridePath)) {
            unlink($this->overridePath);
        }

        parent::tearDown();
    }

    public function test_path_defaults_to_the_storage_logs_directory(): void
    {
        $writer = new PublicContentParityReportWriter();

        self::assertStringEndsWith('/storage/logs/public-content-parity.jsonl', $writer->path());
    }

    public function test_path_uses_the_env_override_when_present(): void
    {
        $this->overridePath = sys_get_temp_dir() . '/parity-report-' . uniqid() . '.jsonl';
        putenv('PUBLIC_CONTENT_PARITY_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentParityReportWriter();

        self::assertSame($this->overridePath, $writer->path());
    }

    public function test_append_writes_a_json_line_to_the_configured_path(): void
    {
        $this->overridePath = sys_get_temp_dir() . '/parity-report-' . uniqid() . '.jsonl';
        putenv('PUBLIC_CONTENT_PARITY_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentParityReportWriter();
        $writer->append(['page_id' => 1, 'status' => 'mismatched']);

        self::assertFileExists($this->overridePath);
        $decoded = json_decode(trim((string) file_get_contents($this->overridePath)), true);
        self::assertSame(['page_id' => 1, 'status' => 'mismatched'], $decoded);
    }

    public function test_append_appends_rather_than_overwriting(): void
    {
        $this->overridePath = sys_get_temp_dir() . '/parity-report-' . uniqid() . '.jsonl';
        putenv('PUBLIC_CONTENT_PARITY_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentParityReportWriter();
        $writer->append(['n' => 1]);
        $writer->append(['n' => 2]);

        $lines = array_filter(explode("\n", (string) file_get_contents($this->overridePath)));
        self::assertCount(2, $lines);
    }
}