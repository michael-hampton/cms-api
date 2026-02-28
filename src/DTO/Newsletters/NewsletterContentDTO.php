<?php

namespace App\DTO\Newsletters;

use App\Enums\Newsletters\ContentSourceType;

final class NewsletterContentDTO
{
    public function __construct(
        public readonly ContentSourceType $contentType,
        public readonly ?array            $blocks,
        public readonly ?string           $legacyContent,
    )
    {
    }

    public static function fromRequest(array $data): self
    {
        $type = ContentSourceType::from($data['content_type'] ?? 'manual');

        return new self(
            contentType: $type,
            blocks: $type === ContentSourceType::CustomBlocks
                ? ($data['content_blocks'] ?? [])
                : null,
            legacyContent: $type === ContentSourceType::Manual
                ? ($data['content'] ?? null)
                : null,
        );
    }

    public function isCustomBlocks(): bool
    {
        return $this->contentType === ContentSourceType::CustomBlocks;
    }

    public function isLegacy(): bool
    {
        return $this->contentType === ContentSourceType::Manual;
    }

    public function isAutomated(): bool
    {
        return $this->contentType === ContentSourceType::AutoPages;
    }
}