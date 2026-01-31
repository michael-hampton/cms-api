<?php

namespace App\Services\Cms\Pages;

use App\Enums\PageStatus;
use App\Models\Page;

class ClonePermissionChecker
{
    /**
     * Check if a page can be cloned by the given user
     *
     * @param Page $page
     * @param int $userId
     * @return bool
     */
    public function canClone(Page $page, int $userId): bool
    {
        $status = is_string($page->status)
            ? PageStatus::from($page->status)
            : $page->status;

        return match ($status) {
            PageStatus::PRIVATE => $this->canClonePrivatePage($page, $userId),
            PageStatus::ON_HOLD => false,
            default => true,
        };
    }

    /**
     * Check if a private page can be cloned by the user
     *
     * @param Page $page
     * @param int $userId
     * @return bool
     */
    private function canClonePrivatePage(Page $page, int $userId): bool
    {
        return $page->created_by === $userId;
    }

    /**
     * Get the reason why a page cannot be cloned
     *
     * @param Page $page
     * @param int $userId
     * @return string|null
     */
    public function getCloneRestrictionReason(Page $page, int $userId): ?string
    {
        $status = is_string($page->status)
            ? PageStatus::from($page->status)
            : $page->status;

        return match ($status) {
            PageStatus::PRIVATE => $this->canClonePrivatePage($page, $userId)
                ? null
                : 'Only the creator can clone private pages',
            PageStatus::ON_HOLD => 'Pages on hold cannot be cloned',
            default => null,
        };
    }
}