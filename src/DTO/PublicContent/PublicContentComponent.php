<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentComponent
{
    public function __construct(
        public string $id,
        public string $type,
        public string $region,
        public int $priority,
        public string $html,
        public array $styles = [],
        public array $scripts = [],
        public array $endpoints = [],
        public bool $stateful = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'region' => $this->region,
            'priority' => $this->priority,
            'html' => $this->html,
            'assets' => [
                'styles' => $this->styles,
                'scripts' => $this->scripts,
            ],
            'endpoints' => $this->endpoints,
            'stateful' => $this->stateful,
        ];
    }
}
