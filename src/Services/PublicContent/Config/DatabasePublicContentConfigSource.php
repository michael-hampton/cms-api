<?php

namespace App\Services\PublicContent\Config;

use App\Framework\Support\Config\ConfigEntry;
use App\Framework\Support\Config\ConfigModel;
use App\Repositories\PublicContent\ConfigDocumentRepository;

final class DatabasePublicContentConfigSource implements PublicContentConfigSource
{
    private const string TYPE = 'public_content';

    /** @var array<int, array<string, mixed>|null> */
    private array $payloadCache = [];

    public function __construct(
        private readonly ConfigDocumentRepository $configDocuments,
    ) {
    }

    public function has(int $siteId): bool
    {
        return $this->payload($siteId) !== null;
    }

    public function get(int $siteId, string $key, mixed $default = null): mixed
    {
        $payload = $this->payload($siteId);

        return $payload === null ? $default : $this->dotGet($payload, $key, $default);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(int $siteId): ?array
    {
        if (!array_key_exists($siteId, $this->payloadCache)) {
            $document = $this->configDocuments->findByType(self::TYPE, $siteId);
            $this->payloadCache[$siteId] = $this->normalizePayload($document?->payload);
        }

        return $this->payloadCache[$siteId];
    }

    /**
     * Accept both associative documents and MigrateConfig-style entry lists.
     *
     * @param array<mixed>|null $payload
     * @return array<string, mixed>|null
     */
    private function normalizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (
            $payload !== []
            && array_is_list($payload)
            && is_array($payload[0] ?? null)
            && array_key_exists('key', $payload[0])
        ) {
            $entries = [];

            foreach ($payload as $item) {
                if (!is_array($item) || !array_key_exists('key', $item)) {
                    continue;
                }

                $entries[] = new ConfigEntry(
                    (string) $item['key'],
                    $item['value'] ?? null,
                    isset($item['id']) ? (string) $item['id'] : null,
                );
            }

            return (new ConfigModel($entries))->toArray();
        }

        return $payload;
    }

    private function dotGet(array $payload, string $key, mixed $default): mixed
    {
        $value = $payload;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
