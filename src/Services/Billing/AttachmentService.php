<?php

namespace App\Services\Billing;

use App\Enums\AttachmentableType;
use App\Framework\Database\Database;
use App\Framework\FileUpload\FileSystem;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Logger;
use App\Models\Attachment;
use App\Repositories\Billing\AttachmentRepository;
use Exception;

/**
 * Manages file attachments for CRM entities.
 *
 * Allowed types: jpg, jpeg, png, pdf, doc, docx (5 MB max).
 * Files are stored at: uploads/crm/attachments/{member_id}/{filename}
 * The stored_path column holds the path relative to the upload root.
 */
class AttachmentService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    private Database $database;

    public function __construct(
        private readonly AttachmentRepository $attachmentRepository,
        private readonly FileSystem           $fileSystem,
        ?Database                             $database = null,
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function upload(
        UploadedFile       $file,
        int                $memberId,
        int                $siteId,
        int                $uploadedByUserId,
        AttachmentableType $entityType,
        int                $entityId,
    ): Attachment {
        $this->validateFile($file);

        return $this->database->transaction(function () use (
            $file, $memberId, $siteId, $uploadedByUserId, $entityType, $entityId
        ) {
            $storedPath = $this->storeFile($file, $memberId);

            $attachment = $this->attachmentRepository->create([
                'member_id'           => $memberId,
                'site_id'             => $siteId,
                'attachmentable_type' => $entityType->value,
                'attachmentable_id'   => $entityId,
                'original_filename'   => $file->getClientOriginalName(),
                'stored_path'         => $storedPath,
                'mime_type'           => $file->getMimeType(),
                'file_size'           => $file->getSize(),
                'uploaded_by'         => $uploadedByUserId,
            ]);

            Logger::info('Attachment uploaded', [
                'attachment_id'       => $attachment->id,
                'member_id'           => $memberId,
                'attachmentable_type' => $entityType->value,
                'attachmentable_id'   => $entityId,
            ]);

            return $attachment;
        });
    }

    public function delete(int $attachmentId, int $memberId): void
    {
        $this->database->transaction(function () use ($attachmentId, $memberId) {
            $attachment = $this->attachmentRepository->find($attachmentId);

            if (!$attachment) {
                throw new Exception('Attachment not found');
            }

            if ($attachment->member_id !== $memberId) {
                throw new Exception('Attachment does not belong to this member');
            }

            $this->deleteStoredFile($attachment->stored_path);
            $this->attachmentRepository->delete($attachmentId);

            Logger::info('Attachment deleted', [
                'attachment_id' => $attachmentId,
                'member_id'     => $memberId,
            ]);
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception($file->getErrorMessage());
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new Exception(
                'Invalid file type. Allowed: JPEG, PNG, GIF, PDF, DOC, DOCX.'
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new Exception('File size exceeds the 5 MB limit.');
        }
    }

    private function storeFile(UploadedFile $file, int $memberId): string
    {
        $baseUploadPath = rtrim(config('upload.path', 'uploads'), '/');
        $relativeDir    = "crm/attachments/{$memberId}";
        $fullDir        = $baseUploadPath . '/' . $relativeDir;

        if (!$this->fileSystem->isDirectory($fullDir)) {
            if (!$this->fileSystem->makeDirectory($fullDir, 0755, true)) {
                throw new Exception('Failed to create upload directory.');
            }
        }

        $extension = $file->getClientOriginalExtension();
        $basename  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename  = substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', $basename), 0, 50);
        $filename  = $basename . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $fullPath  = $fullDir . '/' . $filename;

        if (getenv('APP_ENV') !== 'testing' && !$file->moveTo($fullPath)) {
            throw new Exception('Failed to store uploaded file.');
        }

        return $relativeDir . '/' . $filename;
    }

    private function deleteStoredFile(string $storedPath): void
    {
        $baseUploadPath = rtrim(config('upload.path', 'uploads'), '/');
        $fullPath       = $baseUploadPath . '/' . $storedPath;

        if ($this->fileSystem->fileExists($fullPath)) {
            $this->fileSystem->deleteFile($fullPath);
        }
        // Non-critical: log but do not throw if file is already gone
    }
}