<?php

namespace App\DTO\PublicContent\Layout;

use App\Enums\PublicContent\LayoutResolutionSource;
use App\Enums\PublicContent\LayoutResolutionStatus;

/**
 * Typed layout-precedence outcome. NoLayoutResolved is explicit — never a silent default.
 */
final readonly class PublicContentLayoutResolution
{
    private function __construct(
        public LayoutResolutionStatus $status,
        public ?string $template = null,
        public ?LayoutResolutionSource $source = null,
    ) {
    }

    public static function resolved(string $template, LayoutResolutionSource $source): self
    {
        return new self(
            status: LayoutResolutionStatus::Resolved,
            template: $template,
            source: $source,
        );
    }

    public static function none(): self
    {
        return new self(status: LayoutResolutionStatus::NoLayoutResolved);
    }

    public function isResolved(): bool
    {
        return $this->status === LayoutResolutionStatus::Resolved;
    }

    public function isNoLayoutResolved(): bool
    {
        return $this->status === LayoutResolutionStatus::NoLayoutResolved;
    }

    /**
     * @return array{status: string, template: ?string, source: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'template' => $this->template,
            'source' => $this->source?->value,
        ];
    }
}
