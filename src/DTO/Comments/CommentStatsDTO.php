<?php

namespace App\DTO\Comments;

class CommentStatsDTO
{
    public function __construct(
        public readonly int $total,
        public readonly int $approved,
        public readonly int $pending,
        public readonly int $spam
    )
    {
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'approved' => $this->approved,
            'pending' => $this->pending,
            'spam' => $this->spam
        ];
    }
}