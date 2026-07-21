<?php

namespace App\DTO\PublicContent\Social;

/**
 * Share UI state derived from page_social for article / review / buying-guide pages.
 */
final readonly class PageSocialShareState
{
    /**
     * @param list<string> $platforms
     */
    public function __construct(
        public bool $enableSharing,
        public array $platforms,
        public string $shareText,
        public string $shareUrl,
        public ?string $shareHashtags = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enableSharing' => $this->enableSharing,
            'platforms' => $this->platforms,
            'shareText' => $this->shareText,
            'shareUrl' => $this->shareUrl,
            'shareHashtags' => $this->shareHashtags,
        ];
    }
}
