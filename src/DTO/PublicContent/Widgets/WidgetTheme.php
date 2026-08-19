<?php

namespace App\DTO\PublicContent\Widgets;

/**
 * Site-specific design tokens and flattened CSS variables for widget rendering.
 */
final readonly class WidgetTheme
{
    /**
     * @param array<string, mixed> $tokens
     * @param array<string, string> $cssVariables
     */
    public function __construct(
        public int $siteId,
        public array $tokens,
        public array $cssVariables,
    ) {
    }

    public static function empty(int $siteId = 0): self
    {
        return new self($siteId, [], []);
    }

    /**
     * @return array{site_id: int, tokens: array<string, mixed>, css_variables: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'site_id' => $this->siteId,
            'tokens' => $this->tokens,
            'css_variables' => $this->cssVariables,
        ];
    }
}
