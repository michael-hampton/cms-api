<?php

namespace App\Framework\Support;

class PathHelper
{
    /**
     * Return the absolute path to a folder inside the project src directory.
     *
     * Example:
     * PathHelper::src('Database/Migrations')
     *  → /full/path/to/project/src/Database/Migrations
     */
    public static function src(string $relativePath = ''): string
    {
        // Calculate src path relative to Helpers
        $srcRoot = __DIR__ . '/../../../src';
        $srcRoot = str_replace('\\', '/', $srcRoot); // normalize slashes

        if ($relativePath !== '') {
            return $srcRoot . '/' . trim($relativePath, '/');
        }
        return $srcRoot;
    }

    /**
     * Ensure a directory exists; creates it if missing.
     */
    public static function ensureDirectory(string $path, int $permissions = 0755): void
    {
        if (!is_dir($path)) {
            mkdir($path, $permissions, true);
        }
    }
}