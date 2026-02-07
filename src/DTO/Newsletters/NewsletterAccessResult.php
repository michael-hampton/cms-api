<?php

namespace App\DTO\Newsletters;

class NewsletterAccessResult
{
    public function __construct(
        public readonly bool    $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $reasonCode = null
    )
    {
    }

    public static function allowed(): self
    {
        return new self(true);
    }

    public static function denied(string $reasonCode, string $reason): self
    {
        return new self(false, $reason, $reasonCode);
    }

    /**
     * Get logging context
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'reason_code' => $this->reasonCode,
        ];
    }
}