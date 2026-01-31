<?php

namespace App\Services\Cms\Pages;

use App\Framework\Authorization\Auth;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageHistory;
use App\Repositories\Cms\Pages\PageHistoryRepository;
use App\Repositories\Cms\Pages\PageRepository;

class PageHistoryService
{
    private ?array $oldPageData = null;

    public function __construct(
        private PageHistoryRepository $historyRepository,
        private PageRepository $pageRepository
    ) {}

    public function logPageAction(
        int $pageId,
        string $action,
        ?string $description = null,
        ?array $changes = null,
        bool $includeSnapshot = false
    ): PageHistory {
        $page = $this->pageRepository->find($pageId);

        if (!$page) {
            throw new \Exception("Page not found");
        }

        $userId = Auth::id();
        $request = $_SERVER;

        $data = [
            'page_id' => $pageId,
            'user_id' => $userId,
            'site_id' => $page->site_id,
            'action' => $action,
            'description' => $description,
            'changes' => $changes ? json_encode($changes) : null,
            'snapshot' => $includeSnapshot ? json_encode($this->createPageSnapshot($page)) : null,
            'ip_address' => $request['REMOTE_ADDR'] ?? null,
            'user_agent' => $request['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->historyRepository->create($data);
    }

    public function logPageCreated(Page $page): PageHistory
    {
        return $this->logPageAction(
            $page->id,
            'created',
            'Page created',
            null,
            true
        );
    }

    public function logPageWaitingApproval(Page $page): PageHistory
    {
        return $this->logPageAction(
            $page->id,
            'waiting_approval',
            'Page submitted for approval',
            null,
            true
        );
    }

    public function logPageApproved(Page $page, int $userId): PageHistory
    {
        return $this->logPageAction(
            $page->id,
            'approved',
            'Page submitted for approval',
            [
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s')
            ],
            true
        );
    }

    public function logPageRejected(Page $page, int $userId, ?string $reason = null): PageHistory
    {
        return $this->logPageAction(
            $page->id,
            'rejected',
            'Page Rejected',
            [
                'rejected_by' => $userId,
                'rejected_at' => date('Y-m-d H:i:s'),
                'reason' => $reason
            ],
            true
        );
    }

    public function logPagePutOnHold(Page $page, int $userId, ?string $reason = null): PageHistory
    {
        return $this->logPageAction(
            $page->id,
            'on_hold',
            'Page put on hold for review',
            [
                'user_id' => $userId,
                'reason' => $reason
            ],
            true
        );
    }

    public function logPageMadePrivate(Page $page, int $userId): PageHistory
    {
        return $this->logPageAction(
            $page->id,
            'made_private',
            'Page made private',
            [
                'user_id' => $userId
            ],
            true
        );
    }

    public function logPageUpdated(int $pageId, array $oldData, array $newData): PageHistory
    {
        $changes = $this->comparePageData($oldData, $newData);

        return $this->logPageAction(
            $pageId,
            'updated',
            $this->generateUpdateDescription($changes),
            $changes,
            false
        );
    }

    public function logPagePublished(int $pageId): PageHistory
    {
        return $this->logPageAction(
            $pageId,
            'published',
            'Page published',
            ['status' => ['old' => 'draft', 'new' => 'published']],
            true
        );
    }

    public function logPageUnpublished(int $pageId, array $data = []): PageHistory
    {
        return $this->logPageAction(
            $pageId,
            'unpublished',
            'Page unpublished',
            array_merge($data, ['old' => 'draft', 'new' => 'published']),
            false
        );
    }

    public function logPageDuplicated(int $sourcePageId, int $newPageId): PageHistory
    {
        return $this->logPageAction(
            $newPageId,
            'duplicated',
            "Duplicated from page #{$sourcePageId}",
            ['source_page_id' => $sourcePageId],
            true
        );
    }

    public function logPageDeleted(int $pageId, array $pageData): PageHistory
    {
        return $this->logPageAction(
            $pageId,
            'deleted',
            'Page deleted',
            null,
            true
        );
    }

    public function logPageMadeInternal(Page $page, int $userId): PageHistory
    {
        return $this->logPageAction($page->id, 'made_internal', 'Made Internal', [
            'user_id' => $userId,
            'previous_status' => $page->status,
            'new_status' => 'internal'
        ]);
    }

    public function getPageHistory(int $pageId, int $limit = 50): Collection
    {
        return $this->historyRepository->getPageHistory($pageId, $limit);
    }

    public function getRecentHistory(int $siteId, int $limit = 20): Collection
    {
        return $this->historyRepository->getRecentHistory($siteId, $limit);
    }

    public function getUserHistory(int $userId, int $limit = 50): Collection
    {
        return $this->historyRepository->getUserHistory($userId, $limit);
    }

    public function getHistoryEntry(int $historyId): ?PageHistory
    {
        return $this->historyRepository->findById($historyId);
    }

    public function restoreFromHistory(int $historyId): Page
    {
        $history = $this->historyRepository->findById($historyId);

        if (!$history || !$history->snapshot) {
            throw new \Exception("History entry not found or has no snapshot");
        }

        $snapshot = $history->snapshot;
        $page = $this->pageRepository->find($history->page_id);

        if (!$page) {
            throw new \Exception("Page not found");
        }

        // Update page with snapshot data
        $updateData = [
            'title' => $snapshot['title'] ?? $page->title,
            'slug' => $snapshot['slug'] ?? $page->slug,
            'status' => $snapshot['status'] ?? $page->status,
            'meta_title' => $snapshot['meta_title'] ?? $page->meta_title,
            'meta_description' => $snapshot['meta_description'] ?? $page->meta_description
        ];

        $page = $this->pageRepository->update($page->id, $updateData);

        // Log restoration
        $this->logPageAction(
            $page->id,
            'restored',
            "Restored from history entry #{$historyId}",
            ['history_id' => $historyId],
            false
        );

        return $page;
    }

    private function createPageSnapshot(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'published_at' => $page->published_at,
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at
        ];
    }

    private function comparePageData(array $old, array $new): array
    {
        $changes = [];
        $compareFields = ['title', 'slug', 'status', 'meta_title', 'meta_description'];

        foreach ($compareFields as $field) {
            if (isset($old[$field]) && isset($new[$field]) && $old[$field] !== $new[$field]) {
                $changes[$field] = [
                    'old' => $old[$field],
                    'new' => $new[$field]
                ];
            }
        }

        // Compare blocks if present
        if (isset($old['blocks']) && isset($new['blocks'])) {
            $blockChanges = $this->compareBlocks($old['blocks'], $new['blocks']);
            if (!empty($blockChanges)) {
                $changes = array_merge($changes, $blockChanges);
            }
        }

        return $changes;
    }

    private function compareBlocks(array $oldBlocks, array $newBlocks): array
    {
        $oldCount = count($oldBlocks);
        $newCount = count($newBlocks);
        $changes = [];

        if ($newCount > $oldCount) {
            $changes['blocks_added'] = $newCount - $oldCount;
        } elseif ($newCount < $oldCount) {
            $changes['blocks_removed'] = $oldCount - $newCount;
        }

        // Simple modification check
        $modified = 0;
        $minCount = min($oldCount, $newCount);
        for ($i = 0; $i < $minCount; $i++) {
            if (json_encode($oldBlocks[$i]) !== json_encode($newBlocks[$i])) {
                $modified++;
            }
        }

        if ($modified > 0) {
            $changes['blocks_modified'] = $modified;
        }

        return $changes;
    }

    private function generateUpdateDescription(array $changes): string
    {
        if (empty($changes)) {
            return 'Page updated';
        }

        $descriptions = [];

        foreach ($changes as $field => $change) {
            if (is_array($change) && isset($change['old']) && isset($change['new'])) {
                $descriptions[] = ucfirst($field) . " changed";
            } elseif (in_array($field, ['blocks_added', 'blocks_removed', 'blocks_modified'])) {
                $descriptions[] = str_replace('_', ' ', $field);
            }
        }

        return !empty($descriptions) ? implode(', ', $descriptions) : 'Page updated';
    }

    public function cleanupOldHistory(int $days = 90): int
    {
        return $this->historyRepository->deleteOlderThan($days);
    }

    public function logPageClonedToSite(int $sourcePageId, int $targetPageId, int $targetSiteId): Model
    {
        $userId = Auth::id();
        $request = $_SERVER;

        return PageHistory::create([
            'page_id' => $targetPageId,
            'user_id' => $userId,
            'site_id' => $targetSiteId,
            'action' => 'cloned_to_site',
            'description' => 'Clone To Site',
            'changes' => json_encode([
                'source_page_id' => $sourcePageId,
                'target_site_id' => $targetSiteId
            ]),
            'ip_address' => $request['REMOTE_ADDR'] ?? null,
            'user_agent' => $request['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function logPageScheduleUpdated(int $pageId, string $scheduledDate)
    {
        return $this->logPageAction(
            $pageId,
            'schedule_updated',
            'Schedule Updated',
            ['status' => ['scheduled_date' => $scheduledDate]],
            true
        );
    }
}