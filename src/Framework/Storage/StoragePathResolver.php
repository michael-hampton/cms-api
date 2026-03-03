<?php

namespace App\Framework\Storage;

class StoragePathResolver implements StoragePathResolverInterface
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function resolve(string $relativePath): string
    {
        return rtrim($this->basePath, '/') . '/' . ltrim($relativePath, '/');
    }
}