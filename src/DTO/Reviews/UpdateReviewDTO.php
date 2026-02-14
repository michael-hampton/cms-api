<?php

namespace App\DTO\Reviews;

class UpdateReviewDTO
{
    public function __construct(
        public readonly ?int    $rating = null,
        public readonly ?string $title = null,
        public readonly ?string $comment = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rating: isset($data['rating']) ? (int)$data['rating'] : null,
            title: $data['title'] ?? null,
            comment: $data['comment'] ?? null
        );
    }

    public function toArray(): array
    {
        $result = [];

        if ($this->rating !== null) {
            $result['rating'] = $this->rating;
        }

        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        if ($this->comment !== null) {
            $result['comment'] = $this->comment;
        }

        return $result;
    }
}