<?php

namespace App\Services\PublicContent\Config;

use App\DTO\PublicContent\Config\AllowedRegionsArtefact;
use RuntimeException;

/**
 * Loads the versioned allowed-regions artefact. Missing, malformed, or wrong
 * schema version throws — there is no silent fallback to defaults.
 */
final class AllowedRegionsArtefactLoader
{
    public const int EXPECTED_SCHEMA_VERSION = 1;

    public function load(string $absolutePath): AllowedRegionsArtefact
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact is missing: %s',
                $absolutePath,
            ));
        }

        $raw = file_get_contents($absolutePath);

        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact is empty: %s',
                $absolutePath,
            ));
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact is malformed JSON (%s): %s',
                $exception->getMessage(),
                $absolutePath,
            ), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact must decode to an object: %s',
                $absolutePath,
            ));
        }

        if (!isset($decoded['schema_version']) || !is_int($decoded['schema_version'])) {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact requires integer schema_version: %s',
                $absolutePath,
            ));
        }

        if ($decoded['schema_version'] !== self::EXPECTED_SCHEMA_VERSION) {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact has wrong schema_version %d (expected %d): %s',
                $decoded['schema_version'],
                self::EXPECTED_SCHEMA_VERSION,
                $absolutePath,
            ));
        }

        if (!isset($decoded['artefact_version']) || !is_string($decoded['artefact_version']) || trim($decoded['artefact_version']) === '') {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact requires non-empty artefact_version: %s',
                $absolutePath,
            ));
        }

        if (!isset($decoded['regions']) || !is_array($decoded['regions']) || $decoded['regions'] === []) {
            throw new RuntimeException(sprintf(
                'Public content allowed regions artefact requires a non-empty regions list: %s',
                $absolutePath,
            ));
        }

        $regions = [];
        foreach ($decoded['regions'] as $index => $region) {
            if (!is_string($region) || trim($region) === '') {
                throw new RuntimeException(sprintf(
                    'Public content allowed regions artefact region entry %s must be a non-empty string: %s',
                    (string) $index,
                    $absolutePath,
                ));
            }
            $regions[] = strtoupper(trim($region));
        }

        return new AllowedRegionsArtefact(
            schemaVersion: $decoded['schema_version'],
            artefactVersion: $decoded['artefact_version'],
            regions: array_values(array_unique($regions)),
            sourcePath: $absolutePath,
        );
    }
}
