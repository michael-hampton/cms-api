<?php

namespace App\Tests\Unit\Services\PublicContent\Diagnostics;

use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsReportWriter;
use PHPUnit\Framework\TestCase;

final class PublicContentDiagnosticsReportWriterTest extends TestCase
{
    private ?string $overridePath = null;

    protected function tearDown(): void
    {
        putenv('PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH');
        unset($_ENV['PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH']);

        if ($this->overridePath !== null && is_file($this->overridePath)) {
            unlink($this->overridePath);
        }

        parent::tearDown();
    }

    public function test_path_defaults_to_the_storage_logs_directory(): void
    {
        $writer = new PublicContentDiagnosticsReportWriter();

        self::assertStringEndsWith('/storage/logs/public-content-widget-skips.jsonl', $writer->path());
    }

    public function test_path_uses_the_env_override_when_present(): void
    {
        $this->overridePath = sys_get_temp_dir() . '/diagnostics-report-' . uniqid() . '.jsonl';
        putenv('PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentDiagnosticsReportWriter();

        self::assertSame($this->overridePath, $writer->path());
    }

    public function test_append_writes_a_json_line_to_the_configured_path(): void
    {
        $this->overridePath = sys_get_temp_dir() . '/diagnostics-report-' . uniqid() . '.jsonl';
        putenv('PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentDiagnosticsReportWriter();
        $writer->append(['widget' => 'authors', 'reason' => 'no-data']);

        self::assertFileExists($this->overridePath);
        $decoded = json_decode(trim((string) file_get_contents($this->overridePath)), true);
        self::assertSame(['widget' => 'authors', 'reason' => 'no-data'], $decoded);
    }

    public function test_append_appends_rather_than_overwriting(): void
    {
        $this->overridePath = sys_get_temp_dir() . '/diagnostics-report-' . uniqid() . '.jsonl';
        putenv('PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentDiagnosticsReportWriter();
        $writer->append(['n' => 1]);
        $writer->append(['n' => 2]);

        $lines = array_filter(explode("\n", (string) file_get_contents($this->overridePath)));
        self::assertCount(2, $lines);
    }

    public function test_append_creates_the_directory_if_it_does_not_exist(): void
    {
        $dir = sys_get_temp_dir() . '/diagnostics-nested-' . uniqid();
        $this->overridePath = $dir . '/skips.jsonl';
        putenv('PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH=' . $this->overridePath);

        $writer = new PublicContentDiagnosticsReportWriter();
        $writer->append(['ok' => true]);

        self::assertFileExists($this->overridePath);

        unlink($this->overridePath);
        rmdir($dir);
        $this->overridePath = null;
    }
}