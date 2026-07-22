<?php

namespace App\DTO\PublicContent\Config;

/**
 * Versioned design-tokens artefact loaded fail-closed at start-up.
 *
 * @param array<string, mixed> $defaults
 */
final readonly class DesignTokensArtefact
{
    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        public int $schemaVersion,
        public string $artefactVersion,
        public array $defaults,
        public string $sourcePath,
    ) {
    }

    public function envelope(): ConfigArtefactEnvelope
    {
        return new ConfigArtefactEnvelope(
            schemaVersion: $this->schemaVersion,
            artefactVersion: $this->artefactVersion,
            payload: ['defaults' => $this->defaults],
        );
    }
}
