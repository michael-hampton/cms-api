<?php

namespace App\Framework\Http;

class UploadedFile
{
    private array $fileInfo;
    private string $tmpName;
    private string $name;
    private string $type;
    private int $size;
    private int $error;

    public function __construct(array $fileInfo)
    {
        $this->fileInfo = $fileInfo;
        $this->tmpName = $fileInfo['tmp_name'] ?? '';
        $this->name = $fileInfo['name'] ?? '';
        $this->type = $fileInfo['type'] ?? '';
        $this->size = $fileInfo['size'] ?? 0;
        $this->error = $fileInfo['error'] ?? UPLOAD_ERR_NO_FILE;
    }

    public function isValid(): bool
    {
        if ($_ENV['APP_ENV'] === 'testing') {
            return $this->error === UPLOAD_ERR_OK && file_exists($this->tmpName);
        }

        return $this->error === UPLOAD_ERR_OK &&
               is_uploaded_file($this->tmpName) &&
               file_exists($this->tmpName);
    }

    public function getClientOriginalName(): string
    {
        return $this->name;
    }

    public function getClientOriginalExtension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function getMimeType(): string
    {
        if ($this->isValid()) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $this->tmpName);
            finfo_close($finfo);
            return $mimeType ?: $this->type;
        }
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        switch ($this->error) {
            case UPLOAD_ERR_OK:
                return 'No error';
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    public function moveTo(string $destination): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Ensure destination directory exists
        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return move_uploaded_file($this->tmpName, $destination);
    }

    public function getTempName(): string
    {
        return $this->tmpName;
    }

    public static function createFromGlobal(string $key): ?self
    {
        if (!isset($_FILES[$key])) {
            return null;
        }

        return new self($_FILES[$key]);
    }

    public function getFileInfo(): array
    {
        return $this->fileInfo;
    }
}