<?php

namespace App\DTO\PublicContent;

final readonly class InitialPublicContentHero
{
    public function __construct(
        public int $blockId,
        public string $html,
        public ?string $preloadUrl,
    ) {
    }

    public function hasPreload(): bool
    {
        return $this->preloadUrl !== null && trim($this->preloadUrl) !== '';
    }
}
