<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Models\Page;
use App\Repositories\AuthorRepository;
use Exception;

class AuthorService
{
    private Database $database;

    public function __construct(
        private AuthorRepository $authorRepository,
        private ImageUploadService $imageUploadService,
        ?Database $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function getAllAuthors(): Collection
    {
        return Author::orderBy('name', 'asc')->get();
    }

    public function getActiveAuthors(): Collection
    {
        return $this->authorRepository->getActiveAuthors();
    }

    public function getAuthorById(int $id): ?Author
    {
        return Author::with(['pages'])->find($id);
    }

    public function getAuthorBySlug(string $slug): ?Author
    {
        $author = $this->authorRepository->findBySlug($slug);
        if ($author) {
            $author->load(['pages']);
        }
        return $author;
    }

    public function createAuthor(array $data, int $siteId, ?UploadedFile $avatarFile = null): Author
    {
        return $this->database->transaction(function() use ($data, $avatarFile, $siteId) {
            // Handle image upload
            if ($avatarFile && $avatarFile->isValid()) {
                $data['avatar'] = $this->imageUploadService->upload($avatarFile);
            }

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }

            $data['site_id'] = $siteId;

            return $this->authorRepository->create($data);
        });
    }

    public function updateAuthor(int $id, array $data, ?UploadedFile $avatarFile = null): Author
    {
        return $this->database->transaction(function() use ($id, $data, $avatarFile) {
            $author = $this->authorRepository->find($id);

            if (!$author) {
                throw new Exception("Author not found");
            }

            // Handle image upload
            if ($avatarFile && $avatarFile->isValid()) {
                $oldAvatar = $author->avatar;
                $data['avatar'] = $this->imageUploadService->upload($avatarFile, $oldAvatar);
            }

            // 7. Auto-regenerate slug if name changed and slug wasn't explicitly provided
            if (!empty($data['name']) && $data['name'] !== $author->name && empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }

            $updatedAuthor = $this->authorRepository->update($id, $data);

            if (!$updatedAuthor) {
                throw new Exception("Failed to update author");
            }

            return $updatedAuthor;
        });
    }

    public function delete(int $authorId, ?int $reassignToAuthorId = null): bool
    {
        $author = $this->authorRepository->find($authorId);

        if (!$author) {
            throw new \Exception('Author not found');
        }

        $pagesCount = $this->authorRepository->getPagesByAuthorId($authorId)->count();

        if ($pagesCount > 0) {
            if ($reassignToAuthorId === null) {
                throw new CannotDeleteException('author', $pagesCount);
            }

            if ($reassignToAuthorId === $authorId) {
                throw new \InvalidArgumentException('Cannot reassign to the same author being deleted');
            }

            $reassignAuthor = $this->authorRepository->find($reassignToAuthorId);

            if (!$reassignAuthor) {
                throw new \Exception('Reassignment author not found');
            }

            $this->database->transaction(function () use ($authorId, $author, $reassignToAuthorId) {
                // Get pages and update them individually
                $pages = $this->authorRepository->getPagesByAuthorId($authorId);
                foreach ($pages as $page) {
                    $page->author_id = $reassignToAuthorId;
                    $page->save();
                }
                $author->delete();
            });

            return true;
        }

        return $author->delete();
    }

    public function checkDeletable(int $authorId): array
    {
        $author = $this->authorRepository->find($authorId);

        if (!$author) {
            throw new \Exception('Author not found');
        }

        $pagesCount = $author->pages()->count();

        return [
            'can_delete' => $pagesCount === 0,
            'pages_count' => $pagesCount,
            'requires_reassignment' => $pagesCount > 0
        ];
    }

    public function getAlternativeAuthors(int $authorId): Collection
    {
        return $this->authorRepository->getAlternatives($authorId);
    }

    public function mergeAuthors(int $sourceAuthorId, int $targetAuthorId): bool
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

            // Delete source author's avatar
            if ($sourceAuthor->avatar) {
                $this->imageUploadService->delete($sourceAuthor->avatar);
            }

            // Delete source author
            $this->authorRepository->delete($sourceAuthorId);

            return true;
        });
    }

    public function searchAuthors(string $query, ?int $limit = null): Collection
    {
        return $this->authorRepository->searchAuthors($query, $limit);
    }

    private function generateSlug(string $name): string
    {
        return Str::slug($name, [$this->authorRepository, 'findBySlug']);
    }

    public function duplicateAuthor(int $authorId, ?string $newName = null): Author
    {
        return $this->database->transaction(function() use ($authorId, $newName) {
            $originalAuthor = $this->authorRepository->find($authorId);

            if (!$originalAuthor) {
                throw new \Exception("Author not found");
            }

            $data = [
                'name' => $newName ?? ($originalAuthor->name . ' (Copy)'),
                'email' => null, // Email must be unique, so clear it
                'bio' => $originalAuthor->bio,
                'site_id' => SiteContext::getId(),
                'website' => $originalAuthor->website,
                'social_links' => $originalAuthor->social_links,
                'status' => 'inactive', // Set to inactive for review
            ];

            // Generate unique slug
            $data['slug'] = $this->generateSlug($data['name']);

            // Handle avatar duplication
            if ($originalAuthor->avatar) {
                try {
                    $data['avatar'] = $this->imageUploadService->duplicate($originalAuthor->avatar);
                } catch (\Exception $e) {
                    // If duplication fails, just skip the avatar
                    $data['avatar'] = null;
                }
            }

            return $this->authorRepository->create($data);
        });
    }

    public function bulkDelete(array $authorIds): array
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