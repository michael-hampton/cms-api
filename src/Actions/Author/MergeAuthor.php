<?php

namespace App\Actions\Author;

use App\Framework\Database\Database;
use App\Repositories\Cms\AuthorRepository;
use App\Services\Cms\ImageUploadService;
use Exception;

/**
 * MergeAuthor reassigns all pages from the source author to the target author
 * and then deletes the source.
 *
 * The new expertise / location / education / awards / seniority_date / is_active
 * fields require no special merge handling here: the target author retains its
 * own values for those fields, which is the correct semantic for a merge
 * (the surviving author keeps their own profile data).
 *
 * If a future requirement arises to merge profile fields (e.g. concatenate
 * award lists), that logic should live in a dedicated ProfileMergeStrategy
 * collaborator, not in this action.
 */
class MergeAuthor
{
    private Database $database;

    public function __construct(
        private AuthorRepository $authorRepository,
        private ImageUploadService $imageUploadService,
        ?Database                $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $sourceAuthorId, int $targetAuthorId): array
    {
        if ($sourceAuthorId === $targetAuthorId) {
            throw new Exception("Cannot merge an author with itself");
        }

        return $this->database->transaction(function () use ($sourceAuthorId, $targetAuthorId) {
            $results = [
                'success' => [],
                'failed' => [],
                'pages_reassigned' => 0,
            ];

            $sourceAuthor = $this->authorRepository->find($sourceAuthorId);
            $targetAuthor = $this->authorRepository->find($targetAuthorId);

            if (!$sourceAuthor || !$targetAuthor) {
                throw new Exception("One or both authors not found");
            }

            // Reassign all pages from source to target
            $pages = $this->authorRepository->getPagesByAuthorId($sourceAuthorId);

            foreach ($pages as $page) {
                try {
                    $page->author_id = $targetAuthorId;
                    $page->save();
                    $results['pages_reassigned']++;
                } catch (Exception $e) {
                    $results['failed'][] = [
                        'operation' => 'reassign_page',
                        'page_id' => $page->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $results['success'][] = 'pages_reassigned';

            // Record merge history on both sides
            try {
                $targetAuthor->addCloneRecord('merged_from', $sourceAuthor->id, null);
                $sourceAuthor->addCloneRecord('merged_to', $targetAuthor->id, null);
                $results['success'][] = 'merge_history';
            } catch (Exception $e) {
                $results['failed'][] = [
                    'operation' => 'merge_history',
                    'error' => $e->getMessage(),
                ];
            }

            // Delete source author's avatar (non-critical — log and continue)
            if ($sourceAuthor->avatar) {
                try {
                    $this->imageUploadService->delete($sourceAuthor->avatar);
                    $results['success'][] = 'avatar_deleted';
                } catch (Exception $e) {
                    $results['failed'][] = [
                        'operation' => 'delete_avatar',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Delete source author (critical — re-throw to rollback)
            try {
                $this->authorRepository->delete($sourceAuthorId);
                $results['success'][] = 'author_deleted';
            } catch (Exception $e) {
                $results['failed'][] = [
                    'operation' => 'delete_author',
                    'error' => $e->getMessage(),
                ];
                throw $e;
            }

            return [
                'success' => true,
                'results' => $results,
                'source_author_id' => $sourceAuthorId,
                'target_author_id' => $targetAuthorId,
            ];
        });
    }
}