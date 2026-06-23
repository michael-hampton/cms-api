<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentComponent
{
    public const HYDRATION_NONE = 'none';
    public const HYDRATION_VISIBLE = 'visible';
    public const HYDRATION_IDLE = 'idle';
    public const HYDRATION_INTERACTION = 'interaction';
    public const HYDRATION_LOAD = 'load';

    private const HYDRATION_STRATEGIES = [
        self::HYDRATION_NONE,
        self::HYDRATION_VISIBLE,
        self::HYDRATION_IDLE,
        self::HYDRATION_INTERACTION,
        self::HYDRATION_LOAD,
    ];

    public string $hydration;

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
        string $hydration = self::HYDRATION_NONE,
    ) {
        $this->hydration = in_array($hydration, self::HYDRATION_STRATEGIES, true)
            ? $hydration
            : self::HYDRATION_NONE;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'region' => $this->region,
            'priority' => $this->priority,
            'html' => $this->islandHtml(),
            'assets' => [
                'styles' => $this->styles,
                'scripts' => $this->scripts,
            ],
            'endpoints' => $this->endpoints,
            'stateful' => $this->stateful,
            'hydration' => $this->hydration,
        ];
    }

    private function islandHtml(): string
    {
        $attributes = [
            'data-component-id' => $this->id,
            'data-component-type' => $this->type,
            'data-stateful' => $this->stateful ? 'true' : 'false',
            'data-hydration' => $this->hydration,
        ];

        if ($this->stateful) {
            $attributes['data-island'] = $this->type;
        }

        if ($this->endpoints !== []) {
            $attributes['data-props'] = json_encode([
                'endpoints' => $this->endpoints,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return sprintf(
            '<div %s>%s</div>',
            $this->renderAttributes($attributes),
            $this->html,
        );
    }

    /** @param array<string, string> $attributes */
    private function renderAttributes(array $attributes): string
    {
        $html = [];

        foreach ($attributes as $name => $value) {
            $html[] = sprintf(
                '%s="%s"',
                $name,
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return implode(' ', $html);
    }
}
