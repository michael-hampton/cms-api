<?php

namespace App\Framework\Storage;

interface StoragePathResolverInterface
{
    public function resolve(string $relativePath): string;
}