<?php
namespace App\Services\PublicContent\Theming;

use App\Repositories\PublicContent\ConfigDocumentRepository;

final class DatabasePublicContentDesignTokenSource implements PublicContentDesignTokenSource
{
    private const string TYPE = 'public_content_design_tokens';

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

    public function defaults(int $siteId): array
    {
        return (array) ($this->payload($siteId)['defaults'] ?? []);
    }

    public function overrides(int $siteId): array
    {
        return (array) ($this->payload($siteId)['overrides'] ?? []);
    }

    /** @return array<string, mixed>|null */
    private function payload(int $siteId): ?array
    {
        if (!array_key_exists($siteId, $this->payloadCache)) {
            $document = $this->configDocuments->findByType(self::TYPE, $siteId);
            $this->payloadCache[$siteId] = $document?->payload;
        }

        return $this->payloadCache[$siteId];
    }
}