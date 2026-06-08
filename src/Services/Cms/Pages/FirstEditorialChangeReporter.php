<?php

namespace App\Services\Cms\Pages;

use App\Events\Cms\ContentEditoriallyModified;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;

class FirstEditorialChangeReporter
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    public function reportIfNeeded(Page $page, int $actorId, int $pageHistoryId): bool
    {
        if (!$this->shouldReport($page, $actorId, $pageHistoryId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $this->pageRepository->update((int) $page->id, [
            'first_editorial_change_reported_at' => $now,
            'first_editorial_change_reported_by' => $actorId,
            'first_editorial_change_history_id' => $pageHistoryId,
        ]);

        event(new ContentEditoriallyModified(
            contentType: 'pages',
            contentId: (int) $page->id,
            siteId: (int) $page->site_id,
            actorId: $actorId,
            title: (string) $page->title,
            ownerId: (int) $page->contributor_id,
            historyId: $pageHistoryId,
        ));

        return true;
    }

    private function shouldReport(Page $page, int $actorId, int $pageHistoryId): bool
    {
        if ($pageHistoryId <= 0 || $actorId <= 0) {
            return false;
        }

        if (empty($page->contributor_id)) {
            return false;
        }

        if (!(bool) $page->is_public_contribution) {
            return false;
        }

        if (!empty($page->first_editorial_change_reported_at)) {
            return false;
        }

        if ((int) $page->contributor_id === $actorId) {
            return false;
        }

        return true;
    }
}