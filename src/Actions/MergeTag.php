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

    public function handle(int $fromTagId, int $toTagId): bool
    {
        if ($fromTagId === $toTagId) {
            throw new \InvalidArgumentException('Cannot merge a tag with itself');
        }

        $fromTag = $this->repository->find($fromTagId);
        if (!$fromTag) {
            throw new \Exception('Source tag not found');
        }

        $toTag = $this->repository->find($toTagId);
        if (!$toTag) {
            throw new \Exception('Target tag not found');
        }

        // Add merge history before merging
        $toTag->addCloneRecord('merged_from', $fromTag->id, null);
        $fromTag->addCloneRecord('merged_to', $toTag->id, null);

        return $this->repository->mergeTags($fromTagId, $toTagId);
    }
}