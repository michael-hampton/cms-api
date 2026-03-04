<?php

namespace App\Framework\Storage;

class StoragePathResolver implements StoragePathResolverInterface
{
    public function __construct(private string $basePath = '')
    {
        if (empty($this->basePath)) {
            $this->basePath = getcwd();
        }
    }

    public function resolve(string $relativePath): string
    {
        return rtrim($this->basePath, '/') . '/' . ltrim($relativePath, '/');
    }
}