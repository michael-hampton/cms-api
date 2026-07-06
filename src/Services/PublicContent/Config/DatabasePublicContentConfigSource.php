<?php
namespace App\Services\PublicContent\Config;

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
            $this->payloadCache[$siteId] = $document?->payload;
        }

        return $this->payloadCache[$siteId];
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