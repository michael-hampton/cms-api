<?php

namespace App\DTO\PublicContent\Locale;

use App\Enums\PublicContent\EdgeRedirectReason;

/**
 * Portable edge-redirect outcome. HTTP status/location are decided by the
 * edge caller — this DTO only describes whether a redirect is required.
 */
final readonly class PublicContentEdgeRedirectOutcome
{
    private function __construct(
        public bool $shouldRedirect,
        public EdgeRedirectReason $reason,
        public ?string $targetPath = null,
    ) {
    }

    public static function none(): self
    {
        return new self(
            shouldRedirect: false,
            reason: EdgeRedirectReason::None,
            targetPath: null,
        );
    }

    public static function redirect(EdgeRedirectReason $reason, string $targetPath): self
    {
        return new self(
            shouldRedirect: true,
            reason: $reason,
            targetPath: $targetPath,
        );
    }

    /**
     * @return array{should_redirect: bool, reason: string, target_path: ?string}
     */
    public function toArray(): array
    {
        return [
            'should_redirect' => $this->shouldRedirect,
            'reason' => $this->reason->value,
            'target_path' => $this->targetPath,
        ];
    }
}
