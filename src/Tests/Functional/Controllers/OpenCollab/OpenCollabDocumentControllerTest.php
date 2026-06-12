<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabDocument;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class OpenCollabDocumentControllerTest extends FunctionalTestCase
{
    private string $documentBasePath;

    public function test_authenticated_user_can_preview_and_download_site_document(): void
    {
        $document = $this->createDocument($this->siteId, 'contract.txt', 'Preview body');

        $preview = $this->get("/api/{$this->siteSlug}/open-collab/documents/{$document->id}/preview");
        $this->assertSame(200, $preview->getStatusCode());
        $this->assertSame('Preview body', $preview->getContent());
        $this->assertStringStartsWith('inline;', $preview->getHeader('Content-Disposition'));

        $download = $this->get("/api/{$this->siteSlug}/open-collab/documents/{$document->id}/download");
        $this->assertSame(200, $download->getStatusCode());
        $this->assertSame('Preview body', $download->getContent());
        $this->assertStringStartsWith('attachment;', $download->getHeader('Content-Disposition'));
    }

    public function test_document_from_another_site_cannot_be_accessed(): void
    {
        $otherSite = Site::create([
            'name' => 'Other Site',
            'slug' => 'other-site-' . bin2hex(random_bytes(3)),
        ]);
        $document = $this->createDocument((int)$otherSite->id, 'other.txt', 'Other site body');

        $response = $this->get("/api/{$this->siteSlug}/open-collab/documents/{$document->id}/preview");

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_unknown_document_returns_404(): void
    {
        $response = $this->get("/api/{$this->siteSlug}/open-collab/documents/999999/preview");

        $this->assertSame(404, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentBasePath = sys_get_temp_dir() . '/oc-documents-functional-' . bin2hex(random_bytes(4));
        mkdir($this->documentBasePath, 0755, true);
        Config::set('open_collab.documents.base_path', $this->documentBasePath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->documentBasePath);

        parent::tearDown();
    }

    private function createDocument(int $siteId, string $filename, string $content): OpenCollabDocument
    {
        $document = OpenCollabDocument::create([
            'site_id' => $siteId,
            'category' => 'general_open_collab_document',
            'original_filename' => $filename,
            'stored_filename' => $filename,
            'disk' => 'local',
            'path' => '',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => strlen($content),
            'metadata_json' => [],
        ]);

        $relativePath = "open-collab/sites/{$siteId}/documents/{$document->id}/{$filename}";
        $fullPath = $this->documentBasePath . '/' . $relativePath;
        mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $content);
        $document->update(['path' => $relativePath]);

        return $document->fresh() ?? $document;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }

        rmdir($path);
    }
}
