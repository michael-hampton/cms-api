<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Services\OpenCollab\DocumentContentExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class DocumentContentExtractorTest extends TestCase
{
    private DocumentContentExtractor $extractor;

    public function test_txt_extraction_returns_escaped_html(): void
    {
        $path = $this->tempFile('txt', "Hello <script>alert('x')</script>\nSecond line");

        $result = $this->extractor->extract($path, 'txt');

        $this->assertSame('html', $result->format);
        $this->assertSame('completed', $result->status);
        $this->assertStringContainsString('&lt;script&gt;', $result->content);
        $this->assertStringContainsString('<br />', $result->content);
    }

    public function test_markdown_extraction_returns_safe_html_and_needs_review(): void
    {
        $path = $this->tempFile('md', "# Heading\n\n<script>bad()</script>");

        $result = $this->extractor->extract($path, 'md');

        $this->assertSame('html', $result->format);
        $this->assertSame('needs_review', $result->status);
        $this->assertStringContainsString('&lt;script&gt;', $result->content);
    }

    public function test_docx_extraction_returns_readable_content_and_needs_review(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        $path = $this->tempFile('docx', '');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Contributor terms</w:t></w:r></w:p><w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        $result = $this->extractor->extract($path, 'docx');

        $this->assertSame('html', $result->format);
        $this->assertSame('needs_review', $result->status);
        $this->assertStringContainsString('Contributor terms', $result->content);
        $this->assertStringContainsString('Second paragraph', $result->content);
    }

    public function test_pdf_extraction_returns_pdf_document_mode(): void
    {
        $path = $this->tempFile('pdf', '%PDF-1.4');

        $result = $this->extractor->extract($path, 'pdf');

        $this->assertNull($result->content);
        $this->assertSame('pdf', $result->format);
        $this->assertSame('needs_review', $result->status);
    }

    public function test_txt_extraction_escapes_html(): void
    {
        $path = $this->tempFile('txt', '<script>alert("xss")</script>Hello');

        $result = $this->extractor->extract($path, 'txt');

        $this->assertSame('html', $result->format);
        $this->assertSame('completed', $result->status);
        $this->assertStringContainsString('&lt;script&gt;', $result->content);
        $this->assertStringNotContainsString('<script>', $result->content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new DocumentContentExtractor();
    }

    private function tempFile(string $extension, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oc_doc_') . '.' . $extension;
        file_put_contents($path, $contents);

        return $path;
    }
}
