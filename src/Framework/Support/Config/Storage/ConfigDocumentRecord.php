<?php

namespace App\Framework\Support\Config\Storage;

use App\Framework\Support\Config\ConfigModel;

final class ConfigDocumentRecord
{
    public function __construct(
        public readonly string $type,
        public readonly ConfigModel $model,
        public readonly string $fingerprint,
        public readonly ?string $updatedBy,
        public readonly string $updatedAt,
        public readonly ?string $publishedAt,
    ) {
    }
}