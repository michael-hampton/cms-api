<?php

namespace App\Events\PublicContent;

class PublicContentDefaultLocaleApplied
{
    public function __construct(
        public readonly int $siteId,
        public readonly int $pageId,
        public readonly string $defaultLanguage,
    ) {
    }
}
