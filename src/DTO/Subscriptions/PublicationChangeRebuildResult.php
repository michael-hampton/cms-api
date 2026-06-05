<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

class PublicationChangeRebuildResult
{
    public function __construct(
        public readonly ?int $oldEditionId,
        public readonly ?int $newEditionId,
        public readonly int $remainingIssuesTransferred,
    ) {}
}