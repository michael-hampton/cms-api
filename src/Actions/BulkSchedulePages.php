<?php

namespace App\Actions;

use App\Repositories\PageRepository;

class BulkSchedulePages
{
    public function __construct(
        private readonly PageRepository $pageRepository
    )
    {
    }

    /**
     * Bulk schedule pages
     *
     * @param array $schedules Array of ['page_id' => int, 'scheduled_date' => string]
     * @return array
     */
    public function handle(array $schedules): array
    {
        $results = [];

        foreach ($schedules as $schedule) {
            $pageId = $schedule['page_id'];
            $scheduledDate = $schedule['scheduled_date'];

            try {
                $page = $this->pageRepository->find($pageId);

                if (!$page) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Page not found'
                    ];
                    continue;
                }

                // Update page with scheduled status and date
                $updated = $this->pageRepository->update($pageId, [
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledDate
                ]);

                if ($updated) {
                    $results[$pageId] = [
                        'success' => true,
                        'scheduled_date' => $scheduledDate
                    ];
                } else {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Failed to update page'
                    ];
                }
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