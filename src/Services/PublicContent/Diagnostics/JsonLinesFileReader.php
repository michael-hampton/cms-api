<?php

namespace App\Services\PublicContent\Diagnostics;

class JsonLinesFileReader
{
    /** @return list<array<string, mixed>> newest-first */
    public function tail(string $path, int $limit): array
    {
        if (!is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $lines = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $lines[] = $line;

                if (count($lines) > $limit) {
                    array_shift($lines);
                }
            }
        } finally {
            fclose($handle);
        }

        $records = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return array_reverse($records);
    }
}