<?php

namespace App\DTO\PublicContent\Config;

/**
 * Versioned allowed-regions artefact loaded fail-closed at start-up.
 *
 * @param list<string> $regions
 */
final readonly class AllowedRegionsArtefact
{
    /**
     * @param list<string> $regions
     */
    public function __construct(
        public int $schemaVersion,
        public string $artefactVersion,
        public array $regions,
        public string $sourcePath,
    ) {
    }

    public function allows(string $region): bool
    {
        $needle = strtoupper(trim($region));

        foreach ($this->regions as $allowed) {
            if (strtoupper((string) $allowed) === $needle) {
                return true;
            }
        }

        return false;
    }

    public function envelope(): ConfigArtefactEnvelope
    {
        return new ConfigArtefactEnvelope(
            schemaVersion: $this->schemaVersion,
            artefactVersion: $this->artefactVersion,
            payload: ['regions' => $this->regions],
        );
    }
}
