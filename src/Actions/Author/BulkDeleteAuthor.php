<?php

namespace App\Actions\Author;

use App\Framework\Database\Database;
use App\Repositories\Cms\AuthorRepository;
use App\Services\Cms\ImageUploadService;

class BulkDeleteAuthor
{
    private Database $database;

    public function __construct(
        private readonly AuthorRepository   $authorRepository,
        private readonly ImageUploadService $imageUploadService,
        ?Database                           $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(array $authorIds): array
    {
        return $this->database->transaction(function() use ($authorIds) {
            $deleted = [];
            $failed = [];

            foreach ($authorIds as $authorId) {
                try {
                    $author = $this->authorRepository->find($authorId);

                    if (!$author) {
                        $failed[] = ['id' => $authorId, 'reason' => 'Author not found'];
                        continue;
                    }

                    $pagesCount = $this->authorRepository->getPagesByAuthorId($authorId)->count();

                    if ($pagesCount > 0) {
                        $failed[] = [
                            'id' => $authorId,
                            'reason' => "Author has {$pagesCount} associated pages"
                        ];
                        continue;
                    }

                    if ($author->avatar) {
                        $this->imageUploadService->delete($author->avatar);
                    }

                    if ($author->delete()) {
                        $deleted[] = $authorId;
                    } else {
                        $failed[] = ['id' => $authorId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $authorId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($authorIds)
            ];
        });
    }
}