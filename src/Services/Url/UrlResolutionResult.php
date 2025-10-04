<?php

namespace App\Services\Url;

use App\Models\Page;

class UrlResolutionResult
{
    public function __construct(
        public readonly string $type,
        public readonly ?Page $page = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $reason = null,
        public readonly ?string $canonicalUrl = null,
        public readonly array $meta = []
    ) {}

    public function isRedirect(): bool
    {
        return $this->type === 'redirect';
    }

    public function isPage(): bool
    {
        return $this->type === 'page';
    }
}