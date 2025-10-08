<?php

namespace App\Framework\FileUpload;

class FileSystem implements FileSystemInterface
{
    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function makeDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool
    {
        if ($this->isDirectory($path)) {
            return true;
        }
        return mkdir($path, $permissions, $recursive);
    }

    public function deleteFile(string $path): bool
    {
        if (!$this->fileExists($path)) {
            return false;
        }
        return unlink($path);
    }

    public function copy(string $source, string $destination): bool
    {
        return copy($source, $destination);
    }

    public function glob(string $pattern): array
    {
        $result = glob($pattern);
        return $result === false ? [] : $result;
    }

    public function fileSize(string $path): int
    {
        return filesize($path);
    }

    public function realpath(string $path): string|false
    {
        return realpath($path);
    }

    public function pathinfo(string $path, int $flags = PATHINFO_ALL): array|string
    {
        return pathinfo($path, $flags);
    }

    public function putContents(string $path, string $contents): int|false
    {
        return file_put_contents($path, $contents);
    }
}