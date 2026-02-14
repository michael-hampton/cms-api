<?php

namespace App\Enums;

enum CommentStatus: string
{
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case SPAM = 'spam';
    case REJECTED = 'rejected';

    public static function fromString(string $status): self
    {
        return match (strtolower($status)) {
            'approved' => self::APPROVED,
            'pending' => self::PENDING,
            'spam' => self::SPAM,
            'rejected' => self::REJECTED,
            default => throw new \InvalidArgumentException("Invalid comment status: {$status}")
        };
    }
}