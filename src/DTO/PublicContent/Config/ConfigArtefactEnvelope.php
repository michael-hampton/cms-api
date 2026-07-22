<?php

namespace App\DTO\PublicContent\Config;

/**
 * Shared build-time artefact envelope for public-content config delivery.
 *
 * @param array<string, mixed> $payload
 */
final readonly class ConfigArtefactEnvelope
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $schemaVersion,
        public string $artefactVersion,
        public array $payload,
    ) {
    }

    /**
     * @return array{schema_version: int, artefact_version: string, payload: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'artefact_version' => $this->artefactVersion,
            'payload' => $this->payload,
        ];
    }
}
