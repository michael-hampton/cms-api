<?php

namespace App\Actions;

use App\Enums\PageStatus;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\PageRepository;
use App\Services\PageService;

class BulkUpdatePageStatus
{
    public function __construct(private readonly PageRepository $pageRepository, private readonly PageService $pageService)
    {

    }

    public function handle(array $pageIds, string $status, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        // Validate status is valid
        try {
            $statusEnum = PageStatus::from($status);
        } catch (\ValueError $e) {
            throw new \Exception('Invalid status value');
        }

        $results = [];

        foreach ($pageIds as $pageId) {
            try {
                $page = $this->pageRepository->find($pageId);

                if (!$page) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Page not found'
                    ];
                    continue;
                }

                // Check if status transition is allowed
                if (!$page->canTransitionTo($statusEnum)) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => "Cannot change status from {$page->status} to {$status}"
                    ];
                    continue;
                }

                // Handle publishing with approval workflow
                $finalStatus = $status;
                if ($statusEnum === PageStatus::PUBLISHED && $page->requiresApproval() && !$page->isApproved()) {
                    $finalStatus = PageStatus::WAITING_APPROVAL->value;
                }

                $updateData = [
                    'id' => $pageId,
                    'status' => $finalStatus,
                    'forms' => [
                        'meta' => ['status' => $finalStatus]
                    ]
                ];

                // Set published_at if publishing
                if ($finalStatus === PageStatus::PUBLISHED->value) {
                    if ($page->status !== PageStatus::PUBLISHED) {
                        $updateData['published_at'] = date('Y-m-d H:i:s');
                    }
                }

                $this->pageService->updatePageWithAllData($pageId, $updateData, $siteId);
                $results[$pageId] = ['success' => true];

            } catch (\Exception $e) {
                $results[$pageId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}