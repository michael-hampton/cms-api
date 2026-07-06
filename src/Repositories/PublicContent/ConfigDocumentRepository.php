<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Framework\Support\Config\ConfigModel;
use App\Framework\Support\Config\Publishing\ConfigFingerprinter;
use App\Framework\Support\Config\Storage\ConfigDocumentRecord;
use App\Models\ConfigDocument;
use App\Repositories\Repository;

class ConfigDocumentRepository extends Repository
{
    public function __construct(
        private readonly ConfigFingerprinter $fingerprinter,
    ) {
      parent::__construct();
    }

    public function save(string $type, ConfigModel $model, int $siteId, ?string $updatedBy = null): ConfigDocumentRecord
    {
        $fingerprint = $this->fingerprinter->fingerprint($model);
        $now = now();

        $doc = ConfigDocument::updateOrInsert(
            [
                'type' => $type,
                'site_id' => $siteId
            ], [
                'payload' => $model->toArray(),
                'fingerprint' => $fingerprint,
                'published_at' => $now
            ]
        );

        return new ConfigDocumentRecord(
            type: $type,
            model: $model,
            fingerprint: $fingerprint,
            updatedBy: $updatedBy,
            updatedAt: $now,
            publishedAt: $now,
        );
    }

    public function findByType(string $type, int $siteId): ?ConfigDocument
    {
        return $this->model->where('type', $type)
            ->where('site_id', $siteId)
            ->first();
    }

    protected function getModelClass(): string
    {
       return ConfigDocument::class;
    }

    public function allByType(string $type): Collection
    {
        return $this->model->where('type', $type)->get();
    }
}