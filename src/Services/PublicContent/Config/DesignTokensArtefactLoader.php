<?php

namespace App\Services\PublicContent\Config;

use App\DTO\PublicContent\Config\DesignTokensArtefact;
use RuntimeException;

/**
 * Loads the versioned design-tokens artefact. Missing, malformed, or wrong
 * schema version throws — there is no silent fallback to defaults.
 */
final class DesignTokensArtefactLoader
{
    public const int EXPECTED_SCHEMA_VERSION = 1;

    public function load(string $absolutePath): DesignTokensArtefact
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact is missing: %s',
                $absolutePath,
            ));
        }

        $raw = file_get_contents($absolutePath);

        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact is empty: %s',
                $absolutePath,
            ));
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact is malformed JSON (%s): %s',
                $exception->getMessage(),
                $absolutePath,
            ), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact must decode to an object: %s',
                $absolutePath,
            ));
        }

        if (!isset($decoded['schema_version']) || !is_int($decoded['schema_version'])) {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact requires integer schema_version: %s',
                $absolutePath,
            ));
        }

        if ($decoded['schema_version'] !== self::EXPECTED_SCHEMA_VERSION) {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact has wrong schema_version %d (expected %d): %s',
                $decoded['schema_version'],
                self::EXPECTED_SCHEMA_VERSION,
                $absolutePath,
            ));
        }

        if (!isset($decoded['artefact_version']) || !is_string($decoded['artefact_version']) || trim($decoded['artefact_version']) === '') {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact requires non-empty artefact_version: %s',
                $absolutePath,
            ));
        }

        if (!isset($decoded['defaults']) || !is_array($decoded['defaults']) || $decoded['defaults'] === []) {
            throw new RuntimeException(sprintf(
                'Public content design tokens artefact requires a non-empty defaults object: %s',
                $absolutePath,
            ));
        }

        return new DesignTokensArtefact(
            schemaVersion: $decoded['schema_version'],
            artefactVersion: $decoded['artefact_version'],
            defaults: $decoded['defaults'],
            sourcePath: $absolutePath,
        );
    }
}
