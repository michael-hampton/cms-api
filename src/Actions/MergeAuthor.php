<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\AuthorRepository;
use App\Services\ImageUploadService;
use Exception;

class MergeAuthor
{
    private Database $database;

    public function __construct(
        private AuthorRepository $authorRepository,
        private ImageUploadService $imageUploadService,
        ?Database $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }
    
    public function handle(int $sourceAuthorId, int $targetAuthorId): bool
    {
        if ($sourceAuthorId === $targetAuthorId) {
            throw new Exception("Cannot merge an author with itself");
        }

        return $this->database->transaction(function() use ($sourceAuthorId, $targetAuthorId) {
            $sourceAuthor = $this->authorRepository->find($sourceAuthorId);
            $targetAuthor = $this->authorRepository->find($targetAuthorId);

            if (!$sourceAuthor || !$targetAuthor) {
                throw new Exception("One or both authors not found");
            }

            // Reassign all pages from source to target
            $pages = $this->authorRepository->getPagesByAuthorId($sourceAuthorId);

            foreach ($pages as $page) {
                $page->author_id = $targetAuthorId;
                $page->save();
            }

            // Add merge history
            $targetAuthor->addCloneRecord('merged_from', $sourceAuthor->id, null);
            $sourceAuthor->addCloneRecord('merged_to', $targetAuthor->id, null);

            // Delete source author's avatar
            if ($sourceAuthor->avatar) {
                $this->imageUploadService->delete($sourceAuthor->avatar);
            }

            // Delete source author
            $this->authorRepository->delete($sourceAuthorId);

            return true;
        });
    }
}