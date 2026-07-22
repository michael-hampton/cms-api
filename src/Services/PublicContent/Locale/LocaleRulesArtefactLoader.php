<?php

namespace App\Services\PublicContent\Locale;

use App\DTO\PublicContent\Locale\LocaleEdgeRedirectRules;
use App\DTO\PublicContent\Locale\LocaleRule;
use App\DTO\PublicContent\Locale\LocaleRulesArtefact;
use RuntimeException;

/**
 * Loads the versioned locale-rules artefact. Missing or malformed input
 * throws — there is no silent fallback to defaults.
 */
final class LocaleRulesArtefactLoader
{
    public const int EXPECTED_SCHEMA_VERSION = 1;

    public function load(string $absolutePath): LocaleRulesArtefact
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact is missing: %s',
                $absolutePath,
            ));
        }

        $raw = file_get_contents($absolutePath);

        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact is empty: %s',
                $absolutePath,
            ));
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact is malformed JSON (%s): %s',
                $exception->getMessage(),
                $absolutePath,
            ), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact must decode to an object: %s',
                $absolutePath,
            ));
        }

        if (!isset($decoded['schema_version']) || !is_int($decoded['schema_version'])) {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact requires integer schema_version: %s',
                $absolutePath,
            ));
        }

        if ($decoded['schema_version'] !== self::EXPECTED_SCHEMA_VERSION) {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact has wrong schema_version %d (expected %d): %s',
                $decoded['schema_version'],
                self::EXPECTED_SCHEMA_VERSION,
                $absolutePath,
            ));
        }

        if (!isset($decoded['locales']) || !is_array($decoded['locales']) || $decoded['locales'] === []) {
            throw new RuntimeException(sprintf(
                'Public content locale rules artefact requires a non-empty locales list: %s',
                $absolutePath,
            ));
        }

        $locales = [];

        foreach ($decoded['locales'] as $index => $row) {
            if (!is_array($row)) {
                throw new RuntimeException(sprintf(
                    'Public content locale rules artefact locale entry %s must be an object: %s',
                    (string) $index,
                    $absolutePath,
                ));
            }

            foreach (['locale', 'language', 'region', 'url_prefix', 'enabled'] as $required) {
                if (!array_key_exists($required, $row)) {
                    throw new RuntimeException(sprintf(
                        'Public content locale rules artefact locale entry %s missing "%s": %s',
                        (string) $index,
                        $required,
                        $absolutePath,
                    ));
                }
            }

            if (!is_bool($row['enabled'])) {
                throw new RuntimeException(sprintf(
                    'Public content locale rules artefact locale entry %s "enabled" must be boolean: %s',
                    (string) $index,
                    $absolutePath,
                ));
            }

            $aliases = $row['aliases'] ?? [];
            if (!is_array($aliases)) {
                throw new RuntimeException(sprintf(
                    'Public content locale rules artefact locale entry %s "aliases" must be an array: %s',
                    (string) $index,
                    $absolutePath,
                ));
            }

            $locales[] = new LocaleRule(
                locale: (string) $row['locale'],
                language: (string) $row['language'],
                region: (string) $row['region'],
                urlPrefix: (string) $row['url_prefix'],
                enabled: $row['enabled'],
                aliases: array_values(array_map('strval', $aliases)),
            );
        }

        $edgeRedirects = LocaleEdgeRedirectRules::fromArray(
            isset($decoded['edge_redirects']) && is_array($decoded['edge_redirects'])
                ? $decoded['edge_redirects']
                : [],
        );

        $artefactVersion = isset($decoded['artefact_version']) && is_string($decoded['artefact_version'])
            ? $decoded['artefact_version']
            : null;

        return new LocaleRulesArtefact(
            schemaVersion: $decoded['schema_version'],
            locales: $locales,
            sourcePath: $absolutePath,
            artefactVersion: $artefactVersion,
            edgeRedirects: $edgeRedirects,
        );
    }
}
