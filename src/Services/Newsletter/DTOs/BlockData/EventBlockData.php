<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class EventBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $description,
        public readonly string  $formattedDate,
        public readonly ?string $location,
        public readonly ?string $ticketUrl,
        public readonly float   $ticketPrice,
        public readonly string  $currency,
        public readonly ?array  $image
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            formattedDate: $data['formatted_start_datetime'] ?? '',
            location: $data['location'] ?? null,
            ticketUrl: $data['ticketUrl'] ?? null,
            ticketPrice: (float)($data['ticketPrice'] ?? 0),
            currency: $data['currency'] ?? '£',
            image: $data['image'] ?? null
        );
    }
}