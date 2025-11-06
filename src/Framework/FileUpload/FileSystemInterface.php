<?php

namespace App\Framework\FileUpload;

interface FileSystemInterface
{
    public function fileExists(string $path): bool;
    public function isDirectory(string $path): bool;
    public function makeDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool;
    public function deleteFile(string $path): bool;
    public function copy(string $source, string $destination): bool;
    public function glob(string $pattern): array;
    public function fileSize(string $path): int;
    public function realpath(string $path): string|false;
    public function pathinfo(string $path, int $flags = PATHINFO_ALL): array|string;
    public function putContents(string $path, string $contents): int|false;
    public function dirname($path);
}