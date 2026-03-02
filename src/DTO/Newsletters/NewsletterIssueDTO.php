<?php

namespace App\DTO\Newsletters;

/**
 * Carries validated data for creating or updating a newsletter issue.
 *
 * All properties are read-only after construction — the DTO is immutable
 * by design to prevent silent mutations between layers.
 */
final class NewsletterIssueDTO
{
    public function __construct(
        public readonly ?string $subject,
        public readonly ?array  $contentBlocks,
        public readonly ?array  $snapshotJson,
        public readonly ?string $scheduledAt,
    )
    {
    }

    /**
     * Build from a raw request payload array.
     * Only whitelisted keys are read; everything else is ignored.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            subject: $data['subject'] ?? null,
            contentBlocks: isset($data['content_blocks']) && is_array($data['content_blocks'])
                ? $data['content_blocks']
                : null,
            snapshotJson: isset($data['snapshot_json']) && is_array($data['snapshot_json'])
                ? $data['snapshot_json']
                : null,
            scheduledAt: $data['scheduled_at'] ?? null,
        );
    }
}