<?php

namespace App\Imports;

use App\Framework\FileUpload\FileSystemInterface;
use RuntimeException;

class CsvParser
{
    public function __construct(
        private readonly FileSystemInterface $fileSystem
    )
    {
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function parse(string $filePath): array
    {
        if (!$this->fileSystem->fileExists($filePath)) {
            throw new RuntimeException("Import file not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open import file: {$filePath}");
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');

            if ($headers === false || empty($headers)) {
                throw new RuntimeException("CSV file is empty or has no header row.");
            }

            $headers = array_map('trim', $headers);
            $rows = [];
            $line = 1; // header was line 1

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $line++;

                if (count($values) !== count($headers)) {
                    // Column mismatch — caller will see this as a malformed row;
                    // we store it with a sentinel key so the importer can skip it.
                    $rows[] = ['__line' => $line, '__malformed' => true];
                    continue;
                }

                $row = array_combine($headers, array_map('trim', $values));
                $row['__line'] = $line;
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}