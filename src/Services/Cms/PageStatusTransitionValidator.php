<?php

namespace App\Services\Cms;

use App\Enums\PageStatus;
use App\Models\Page;

class PageStatusTransitionValidator
{
    /**
     * Get the reason why a transition is not allowed
     *
     * @param Page $page
     * @param PageStatus|string $targetStatus
     * @return string|null
     */
    public function getTransitionRestrictionReason(Page $page, PageStatus|string $targetStatus): ?string
    {
        if (is_string($targetStatus)) {
            $targetStatus = PageStatus::from($targetStatus);
        }

        $currentStatus = is_string($page->status)
            ? PageStatus::from($page->status)
            : $page->status;

        if ($this->canTransitionTo($page, $targetStatus)) {
            return null;
        }

        return sprintf(
            'Cannot transition from %s to %s',
            $currentStatus->value,
            $targetStatus->value
        );
    }

    /**
     * Validate if a status transition is allowed
     *
     * @param Page $page
     * @param PageStatus|string $targetStatus
     * @return bool
     */
    public function canTransitionTo(Page $page, PageStatus|string $targetStatus): bool
    {
        if (is_string($targetStatus)) {
            $targetStatus = PageStatus::from($targetStatus);
        }

        $currentStatus = is_string($page->status)
            ? PageStatus::from($page->status)
            : $page->status;

        return in_array($targetStatus, $this->getAllowedTransitions($currentStatus));
    }

    /**
     * Get allowed transitions for a given status
     *
     * @param PageStatus $status
     * @return array<PageStatus>
     */
    private function getAllowedTransitions(PageStatus $status): array
    {
        return match ($status) {
            PageStatus::DRAFT => [
                PageStatus::PUBLISHED,
                PageStatus::WAITING_APPROVAL,
                PageStatus::PRIVATE,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED,
                PageStatus::INTERNAL,
                PageStatus::SCHEDULED,
            ],
            PageStatus::WAITING_APPROVAL => [
                PageStatus::PUBLISHED, // Only after approval
                PageStatus::DRAFT,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED,
            ],
            PageStatus::PUBLISHED => [
                PageStatus::DRAFT,
                PageStatus::PRIVATE,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED,
            ],
            PageStatus::PRIVATE => [
                PageStatus::DRAFT,
                PageStatus::PUBLISHED,
                PageStatus::WAITING_APPROVAL,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED,
            ],
            PageStatus::ON_HOLD => [
                PageStatus::DRAFT,
                PageStatus::WAITING_APPROVAL,
                PageStatus::PUBLISHED,
                PageStatus::PRIVATE,
                PageStatus::ARCHIVED,
            ],
            PageStatus::INTERNAL => [
                PageStatus::DRAFT,
                PageStatus::WAITING_APPROVAL,
                PageStatus::PUBLISHED,
                PageStatus::PRIVATE,
                PageStatus::ON_HOLD,
                PageStatus::ARCHIVED,
            ],
            PageStatus::ARCHIVED => [
                PageStatus::DRAFT,
                PageStatus::ON_HOLD,
            ],
            PageStatus::SCHEDULED => [
                PageStatus::PUBLISHED,
                PageStatus::DRAFT,
                PageStatus::ARCHIVED,
            ],
        };
    }
}