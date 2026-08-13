<?php

namespace App\Services\PublicContent\Render;

/**
 * Mutable bag carried through the render pipeline. Steps may rewrite shellHtml
 * after build; they must not own locale/link/image policy themselves.
 */
final class PublicContentRenderContext
{
    /** @var list<array{name: string, slot: string}> */
    private array $trace = [];

    public function __construct(
        public string $shellHtml = '',
        public array $attributes = [],
    ) {
    }

    public function record(string $name, string $slot): void
    {
        $this->trace[] = ['name' => $name, 'slot' => $slot];
    }

    /** @return list<array{name: string, slot: string}> */
    public function trace(): array
    {
        return $this->trace;
    }

    /** @return list<string> */
    public function orderedStepNames(): array
    {
        return array_map(static fn(array $entry): string => $entry['name'], $this->trace);
    }
}
