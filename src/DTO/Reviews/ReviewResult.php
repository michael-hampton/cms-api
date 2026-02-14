<?php

namespace App\DTO\Reviews;

use App\Models\Review;

class ReviewResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly string  $message,
        public readonly ?Review $review = null,
        public readonly array   $counts = []
    )
    {
    }

    public static function success(string $message, ?Review $review = null, array $counts = []): self
    {
        return new self(true, $message, $review, $counts);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'message' => $this->message
        ];

        if ($this->review) {
            $result['review'] = $this->review->toArray();
        }

        if (!empty($this->counts)) {
            $result = array_merge($result, $this->counts);
        }

        return $result;
    }
}