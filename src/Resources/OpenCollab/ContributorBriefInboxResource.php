<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;
use App\Framework\Support\SiteContext;
use App\Models\Brief;
use App\Services\OpenCollab\ContributorBriefInboxService;

class ContributorBriefInboxResource extends JsonResource
{
    public function __construct(
        Brief $resource,
        private readonly ContributorBriefInboxService $service,
        private readonly int $contributorId,
    )
    {
        parent::__construct($resource);
    }

    public function toArray(): array
    {
        $assignment = $this->service->assignmentForContributor($this->resource, $this->contributorId);
        $deadline = $this->service->currentDeadline($this->resource);
        $workflowStatus = $this->service->workflowStatus($this->resource);
        $assignmentStatus = $assignment ? $this->service->assignmentStatus($assignment) : 'accepted';

        return [
            'id' => (int)$this->resource->id,
            'title' => (string)$this->resource->title,
            'site' => (string)($this->resource->site?->name ?? $this->resource->site?->slug ?? ''),
            'assignment_status' => $assignmentStatus,
            'assignment_status_label' => $this->label($assignmentStatus),
            'workflow_status' => $workflowStatus,
            'workflow_status_label' => $this->label($workflowStatus),
            'deadline_at' => $deadline,
            'is_overdue' => $this->service->isOverdue($this->resource),
            'last_updated_at' => $this->dateTime($this->resource->last_activity_at ?? $this->resource->updated_at),
            'workspace_url' => '/' . SiteContext::slug() . '/open-collab/briefs/' . (int)$this->resource->id,
        ];
    }

    private function label(string $status): string
    {
        return implode(' ', array_map('ucfirst', explode('_', $status)));
    }

    private function dateTime($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        }

        return null;
    }
}
