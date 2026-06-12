<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\FileUpload\FileSystem;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Config;
use App\Models\OpenCollabDocument;
use App\Repositories\OpenCollab\OpenCollabDocumentRepository;
use App\Services\OpenCollab\DocumentContentExtractor;
use App\Services\OpenCollab\OpenCollabDocumentService;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class OpenCollabDocumentServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OpenCollabDocumentRepository&MockInterface $repository;
    private Database&MockInterface $database;
    private string $basePath;
    private OpenCollabDocumentService $service;

    public function test_store_writes_file_and_metadata(): void
    {
        $file = $this->uploadedFile('Standard Contributor Contract.txt', 'Plain contract text', 'text/plain');
        $createdPayload = null;
        $updatedPayload = null;
        $document = $this->document(['id' => 42, 'site_id' => 7]);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$createdPayload, $document) {
                $createdPayload = $payload;

                return $document;
            });

        $document
            ->shouldReceive('update')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$updatedPayload, $document) {
                $updatedPayload = $payload;
                foreach ($payload as $key => $value) {
                    $document->{$key} = $value;
                }

                return true;
            });
        $document->shouldReceive('fresh')->once()->andReturn($document);

        $result = $this->service->store(
            file: $file,
            siteId: 7,
            category: 'contract_template_source',
            uploadedByUserId: 99,
            metadata: ['note' => 'first import']
        );

        $this->assertSame($document, $result);
        $this->assertSame('Standard Contributor Contract.txt', $createdPayload['original_filename']);
        $this->assertSame('contract_template_source', $createdPayload['category']);
        $this->assertSame('text/plain', $createdPayload['mime_type']);
        $this->assertSame(strlen('Plain contract text'), $createdPayload['size_bytes']);
        $this->assertSame('txt', $createdPayload['extension']);
        $this->assertNotEmpty($createdPayload['checksum']);

        $this->assertStringStartsWith('open-collab/sites/7/documents/42/', $updatedPayload['path']);
        $this->assertSame('completed', $updatedPayload['metadata_json']['extraction']['status']);
        $this->assertFileExists($this->basePath . '/' . $updatedPayload['path']);
    }

    public function test_unsupported_extension_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->store(
            $this->uploadedFile('payload.html', '<script></script>', 'text/html'),
            7,
            'general_open_collab_document'
        );
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->store(
            $this->uploadedFile('large.txt', 'small body', 'text/plain', 11 * 1024 * 1024),
            7,
            'general_open_collab_document'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        $this->basePath = sys_get_temp_dir() . '/oc-documents-' . bin2hex(random_bytes(4));
        mkdir($this->basePath, 0755, true);
        Config::set('open_collab.documents.base_path', $this->basePath);
        Config::set('open_collab.documents.max_upload_mb', 10);
        Config::set('open_collab.documents.allowed_extensions', ['pdf', 'docx', 'txt', 'md']);
        Config::set('open_collab.documents.allowed_mime_types', [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/markdown',
            'application/octet-stream',
        ]);

        $this->repository = Mockery::mock(OpenCollabDocumentRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->database->shouldReceive('transaction')->andReturnUsing(fn(callable $callback) => $callback());

        $this->service = new OpenCollabDocumentService(
            $this->repository,
            new FileSystem(),
            new DocumentContentExtractor(),
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    private function uploadedFile(string $name, string $content, string $type, ?int $size = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'oc_upload_');
        file_put_contents($path, $content);

        return new UploadedFile([
            'tmp_name' => $path,
            'name' => $name,
            'type' => $type,
            'size' => $size ?? strlen($content),
            'error' => UPLOAD_ERR_OK,
        ]);
    }

    private function document(array $attributes): OpenCollabDocument&MockInterface
    {
        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $document->{$key} = $value;
        }

        return $document;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        foreach ($items === false ? [] : $items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }

        rmdir($path);
    }
}
