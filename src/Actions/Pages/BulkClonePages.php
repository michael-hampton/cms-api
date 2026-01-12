<?php

namespace App\Actions\Pages;

use App\Repositories\Cms\PageRepository;

class BulkClonePages
{
    public function __construct(
        private readonly ClonePage      $clonePage,
        private readonly PageRepository $pageRepository
    )
    {
    }

    public function handle(array $pageIds, array $options = []): array
    {
        $results = [];
        $withPrefix = $options['withPrefix'] ?? true;
        $asDraft = $options['asDraft'] ?? true;

        foreach ($pageIds as $pageId) {
            try {
                $cloneResult = $this->clonePage->handle($pageId);

                if (!$cloneResult) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Page not found'
                    ];
                    continue;
                }

                $clonedPage = $cloneResult['page'];

                // Update cloned page based on options
                $updates = [];

                if (!$withPrefix) {
                    // Remove " (Copy)" suffix from title
                    $originalPage = $this->pageRepository->find($pageId);
                    $updates['title'] = $originalPage->title;
                }

                if ($asDraft) {
                    $updates['status'] = 'draft';
                }

                if (!empty($updates)) {
                    $this->pageRepository->update($clonedPage->id, $updates);
                }

                $results[$pageId] = [
                    'success' => true,
                    'cloned_page_id' => $clonedPage->id
                ];
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