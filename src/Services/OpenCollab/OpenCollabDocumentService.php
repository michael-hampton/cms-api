<?php

namespace App\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\FileUpload\FileSystem;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Config;
use App\Framework\Support\SiteContext;
use App\Models\Model;
use App\Models\OpenCollabDocument;
use App\Repositories\OpenCollab\OpenCollabDocumentRepository;
use InvalidArgumentException;

class OpenCollabDocumentService
{
    public function __construct(
        private readonly OpenCollabDocumentRepository $documentRepository,
        private readonly FileSystem $fileSystem,
        private readonly DocumentContentExtractor $extractor,
        private readonly Database $database,
    ) {
    }

    public function store(
        UploadedFile $file,
        ?int $siteId,
        string $category,
        ?int $uploadedByUserId = null,
        ?string $documentableType = null,
        ?int $documentableId = null,
        array $metadata = []
    ): OpenCollabDocument {
        $this->validateUploadedFile($file);

        return $this->database->transaction(function () use (
            $file,
            $siteId,
            $category,
            $uploadedByUserId,
            $documentableType,
            $documentableId,
            $metadata
        ): Model {
            $extension = strtolower($file->getClientOriginalExtension());
            $storedFilename = $this->generateStoredFilename($file->getClientOriginalName(), $extension);
            $checksum = $this->checksum($file->getTempName());

            $document = $this->documentRepository->create([
                'site_id' => $siteId,
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'category' => $category,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $storedFilename,
                'disk' => $this->configValue('open_collab.documents.disk', 'local'),
                'path' => '',
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'size_bytes' => $file->getSize(),
                'checksum' => $checksum,
                'uploaded_by_user_id' => $uploadedByUserId,
                'metadata_json' => $metadata,
            ]);

            $relativePath = $this->relativePath($document, $storedFilename);
            $this->storeFile($file, $relativePath);

            $extraction = $this->extractor->extract($this->absolutePath($relativePath), $extension);
            $metadata['extraction'] = [
                'content' => $extraction->content,
                'format' => $extraction->format,
                'status' => $extraction->status,
                'error' => $extraction->error,
            ];

            $document->update([
                'path' => $relativePath,
                'metadata_json' => $metadata,
            ]);

            return $document->fresh() ?? $document;
        });
    }

    public function attach(OpenCollabDocument $document, string $documentableType, int $documentableId): OpenCollabDocument
    {
        $document->update([
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
        ]);

        return $document->fresh() ?? $document;
    }

    public function previewUrl(OpenCollabDocument $document): string
    {
        $site = SiteContext::slug() ?: ($document->site_id ?? 'global');

        return url("/api/{$site}/open-collab/documents/{$document->id}/preview");
    }

    public function downloadUrl(OpenCollabDocument $document): string
    {
        $site = SiteContext::slug() ?: ($document->site_id ?? 'global');

        return url("/api/{$site}/open-collab/documents/{$document->id}/download");
    }

    public function absolutePath(string $relativePath): string
    {
        return rtrim($this->configValue('open_collab.documents.base_path', 'storage/open-collab-documents'), '/') . '/' . ltrim($relativePath, '/');
    }

    private function validateUploadedFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException($file->getErrorMessage());
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->configValue('open_collab.documents.allowed_extensions', ['pdf', 'docx', 'txt', 'md']);

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('Unsupported Open Collab document extension.');
        }

        $maxBytes = (int)$this->configValue('open_collab.documents.max_upload_mb', 10) * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new InvalidArgumentException('Open Collab document exceeds the maximum upload size.');
        }

        $mimeType = $file->getMimeType();
        $allowedMimeTypes = $this->configValue('open_collab.documents.allowed_mime_types', []);

        if ($mimeType === 'application/octet-stream') {
            $this->assertOctetStreamLooksSafe($file->getTempName(), $extension);

            return;
        }

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new InvalidArgumentException('Unsupported Open Collab document MIME type.');
        }
    }

    private function assertOctetStreamLooksSafe(string $path, string $extension): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to inspect uploaded document.');
        }

        $prefix = fread($handle, 8);
        fclose($handle);

        $looksSafe = match ($extension) {
            'pdf' => str_starts_with((string)$prefix, '%PDF'),
            'docx' => str_starts_with((string)$prefix, "PK"),
            'txt', 'md' => true,
            default => false,
        };

        if (!$looksSafe) {
            throw new InvalidArgumentException('Uploaded document content does not match its extension.');
        }
    }

    private function generateStoredFilename(string $originalFilename, string $extension): string
    {
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $basename) ?? '', '-'));
        $slug = substr($slug !== '' ? $slug : 'document', 0, 80);

        return date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '_' . $slug . '.' . $extension;
    }

    private function relativePath(OpenCollabDocument $document, string $storedFilename): string
    {
        $scope = $document->site_id === null ? 'global' : 'sites/' . (int)$document->site_id;

        return "open-collab/{$scope}/documents/{$document->id}/{$storedFilename}";
    }

    private function storeFile(UploadedFile $file, string $relativePath): void
    {
        $fullPath = $this->absolutePath($relativePath);
        $directory = dirname($fullPath);

        if (!$this->fileSystem->isDirectory($directory) && !$this->fileSystem->makeDirectory($directory, 0755, true)) {
            throw new InvalidArgumentException('Failed to create Open Collab document directory.');
        }

        $stored = getenv('APP_ENV') === 'testing'
            ? $this->fileSystem->copy($file->getTempName(), $fullPath)
            : $file->moveTo($fullPath);

        if (!$stored) {
            throw new InvalidArgumentException('Failed to store Open Collab document.');
        }
    }

    private function checksum(string $path): ?string
    {
        return is_file($path) ? hash_file('sha256', $path) ?: null : null;
    }

    private function configValue(string $key, mixed $default): mixed
    {
        $sentinel = new \stdClass();
        $value = Config::get($key, $sentinel);

        return $value === $sentinel ? config($key, $default) : $value;
    }
}
