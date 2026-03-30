<?php

namespace App\DTO\Subscriptions;

use App\Enums\Workflow\WorkflowStageStatus;

final class WorkflowStageResult
{
    public function __construct(
        public readonly WorkflowStageStatus $status,
        public readonly array               $summary = [],
        public readonly ?string             $error = null,
    )
    {
    }

    public static function succeeded(array $summary = []): self
    {
        return new self(WorkflowStageStatus::SUCCEEDED, $summary);
    }

    public static function failed(string $error, array $context = []): self
    {
        return new self(WorkflowStageStatus::FAILED, $context, $error);
    }

    public static function partiallySucceeded(array $summary = []): self
    {
        return new self(WorkflowStageStatus::PARTIAL, $summary);
    }
}