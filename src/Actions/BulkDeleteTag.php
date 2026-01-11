<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\Cms\TagRepository;

class BulkDeleteTag
{
    public function __construct(
        private readonly Database       $database,
        private readonly TagRepository  $repository,
    )
    {
    }
    public function handle(array $tagIds): array
    {
        return $this->database->transaction(function() use ($tagIds) {
            $deleted = [];
            $failed = [];

            foreach ($tagIds as $tagId) {
                try {
                    $tag = $this->repository->find($tagId);

                    if (!$tag) {
                        $failed[] = ['id' => $tagId, 'reason' => 'Tag not found'];
                        continue;
                    }

                    $pagesCount = $this->repository->getPagesByTagId($tagId)->count();

                    if ($pagesCount > 0) {
                        $failed[] = [
                            'id' => $tagId,
                            'reason' => "Tag has {$pagesCount} associated pages"
                        ];
                        continue;
                    }

                    if ($tag->delete()) {
                        $deleted[] = $tagId;
                    } else {
                        $failed[] = ['id' => $tagId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $tagId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($tagIds)
            ];
        });
    }
}