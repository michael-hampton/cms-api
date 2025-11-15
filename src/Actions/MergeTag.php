<?php

namespace App\Actions;

use App\Repositories\TagRepository;

class MergeTag
{
    public function __construct(
        private readonly TagRepository  $repository,
    )
    {
    }

    public function handle(int $fromTagId, int $toTagId): array
    {
        if ($fromTagId === $toTagId) {
            throw new \InvalidArgumentException('Cannot merge a tag with itself');
        }

        $results = [
            'success' => [],
            'failed' => []
        ];

        $fromTag = $this->repository->find($fromTagId);
        if (!$fromTag) {
            throw new \Exception('Source tag not found');
        }

        $toTag = $this->repository->find($toTagId);
        if (!$toTag) {
            throw new \Exception('Target tag not found');
        }

        // Add merge history before merging
        try {
            $toTag->addCloneRecord('merged_from', $fromTag->id, null);
            $fromTag->addCloneRecord('merged_to', $toTag->id, null);
            $results['success'][] = 'merge_history';
        } catch (\Exception $e) {
            $results['failed'][] = [
                'operation' => 'merge_history',
                'error' => $e->getMessage()
            ];
        }

        // Perform the merge (reassigns pages and deletes source tag)
        try {
            $mergeSuccess = $this->repository->mergeTags($fromTagId, $toTagId);

            if ($mergeSuccess) {
                $results['success'][] = 'tags_merged';
            } else {
                $results['failed'][] = [
                    'operation' => 'merge_tags',
                    'error' => 'Merge operation returned false'
                ];
            }

            return [
                'success' => $mergeSuccess,
                'results' => $results,
                'source_tag_id' => $fromTagId,
                'target_tag_id' => $toTagId
            ];
        } catch (\Exception $e) {
            $results['failed'][] = [
                'operation' => 'merge_tags',
                'error' => $e->getMessage()
            ];

            return [
                'success' => false,
                'results' => $results,
                'source_tag_id' => $fromTagId,
                'target_tag_id' => $toTagId
            ];
        }
    }
}