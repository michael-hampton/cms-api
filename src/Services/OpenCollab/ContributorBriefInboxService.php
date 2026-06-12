<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\Collaborator;
use App\Repositories\OpenCollab\ContributorBriefRepository;

class ContributorBriefInboxService
{
    public const FILTERS = [
        'all',
        'awaiting_response',
        'accepted',
        'in_progress',
        'submitted',
        'returned_for_changes',
        'completed',
        'overdue',
    ];

    public function __construct(
        private readonly ContributorBriefRepository $briefs,
        private readonly OpenCollabBriefStatusMapper $statusMapper,
    )
    {
    }

    public function getAssignedBriefs(int $contributorId, int $siteId, array $filters = []): Collection
    {
        $briefs = $this->briefs->assignedBriefsForContributor($contributorId, $siteId);

        $briefs = $briefs->filter(function (Brief $brief) use ($contributorId, $filters) {
            $assignment = $this->assignmentForContributor($brief, $contributorId);

            if (!$assignment) {
                return false;
            }

            if (($filters['search'] ?? '') !== '' && !$this->matchesSearch($brief, $filters['search'])) {
                return false;
            }

            $filter = $filters['filter'] ?? 'all';
            if ($filter === 'all') {
                return true;
            }

            if ($filter === 'overdue') {
                return $this->isOverdue($brief);
            }

            return $this->assignmentStatus($assignment) === $filter
                || $this->workflowStatus($brief) === $filter;
        });

        return $briefs->values();
    }

    public function summarize(Collection $briefs, int $contributorId): array
    {
        $summary = [
            'awaiting_response' => 0,
            'in_progress' => 0,
            'submitted' => 0,
            'returned_for_changes' => 0,
            'overdue' => 0,
        ];

        foreach ($briefs as $brief) {
            $assignment = $this->assignmentForContributor($brief, $contributorId);
            $assignmentStatus = $assignment ? $this->assignmentStatus($assignment) : null;
            $workflowStatus = $this->workflowStatus($brief);

            if ($assignmentStatus === 'awaiting_response') {
                $summary['awaiting_response']++;
            }

            if ($workflowStatus === 'in_progress') {
                $summary['in_progress']++;
            }

            if ($workflowStatus === 'submitted') {
                $summary['submitted']++;
            }

            if ($workflowStatus === 'returned_for_changes') {
                $summary['returned_for_changes']++;
            }

            if ($this->isOverdue($brief)) {
                $summary['overdue']++;
            }
        }

        return $summary;
    }

    public function normalizeFilters(array $input): array
    {
        $filter = strtolower(trim((string)($input['filter'] ?? 'all')));
        $search = trim(strip_tags((string)($input['search'] ?? '')));

        if (!in_array($filter, self::FILTERS, true)) {
            throw new \InvalidArgumentException('Invalid brief filter.');
        }

        if (mb_strlen($search) > 120) {
            throw new \InvalidArgumentException('Search must be 120 characters or fewer.');
        }

        return [
            'filter' => $filter,
            'search' => $search,
        ];
    }

    public function assignmentStatus(Collaborator $assignment): string
    {
        return $this->statusMapper->assignmentStatus($assignment);
    }

    public function workflowStatus(Brief $brief): string
    {
        return $this->statusMapper->workflowStatus($brief);
    }

    public function currentDeadline(Brief $brief): ?string
    {
        $deadline = $brief->deadlines?->first(fn($item) => !empty($item->due_date));

        if (!$deadline || empty($deadline->due_date)) {
            return null;
        }

        if ($deadline->due_date instanceof \DateTimeInterface) {
            return $deadline->due_date->format(DATE_ATOM);
        }

        return (string) $deadline->due_date;
    }

    public function isOverdue(Brief $brief): bool
    {
        $deadline = $this->currentDeadline($brief);

        if ($deadline === null) {
            return false;
        }

        return strtotime($deadline) < time()
            && !in_array($this->workflowStatus($brief), ['approved', 'published', 'completed'], true);
    }

    public function assignmentForContributor(Brief $brief, int $contributorId): ?Collaborator
    {
        return $brief->collaborators?->first(
            fn($assignment) => (int)$assignment->user_id === $contributorId
        );
    }

    private function matchesSearch(Brief $brief, string $search): bool
    {
        $needle = mb_strtolower($search);
        $title = mb_strtolower((string)$brief->title);
        $site = mb_strtolower((string)($brief->site?->name ?? $brief->site?->slug ?? ''));

        return str_contains($title, $needle) || str_contains($site, $needle);
    }
}
