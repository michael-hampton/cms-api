<?php

namespace App\DTO\OpenCollab;

final readonly class ContentRiskDetectionInput
{
    public function __construct(
        public int $siteId,
        public int $pageId,
        public ?int $pageVersionId,
        public string $title,
        public string $content,
        public array $blocks,
    ) {
    }
}