<?php

namespace App\Services\OpenCollab\Dashboard;

/**
 * Builds the standard widget API envelope.
 *
 * All widget endpoints MUST return this shape:
 *
 * {
 *   "key":   "earnings",
 *   "title": "Earnings",
 *   "data":  { ... },
 *   "meta":  { "loaded_at": "2026-05-19T10:00:00Z" }
 * }
 *
 * Usage:
 *   return WidgetResponse::make('earnings', 'Earnings', $data);
 *   return WidgetResponse::make('earnings', 'Earnings', $data, ['source' => 'cache']);
 */
final class WidgetResponse
{
    private function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly array  $data,
        private readonly array  $meta,
    ) {}

    public static function make(
        string $key,
        string $title,
        array  $data,
        array  $meta = [],
    ): self {
        return new self($key, $title, $data, $meta);
    }

    public function toArray(): array
    {
        return [
            'key'   => $this->key,
            'title' => $this->title,
            'data'  => $this->data,
            'meta'  => array_merge(
                ['loaded_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
                $this->meta,
            ),
        ];
    }
}