<?php

namespace App\Services\Cms;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Repositories\Cms\AuthorRepository;
use App\Services\OpenCollab\ContributorAuthorSyncService;
use Exception;

class AuthorService
{
    private Database $database;

    public function __construct(
        private readonly AuthorRepository   $authorRepository,
        private readonly ImageUploadService $imageUploadService,
        private readonly ?ContributorAuthorSyncService $contributorAuthorSyncService,
        ?Database                           $database = null,
    )
    {
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
        return $this->database->transaction(function () use ($data, $avatarFile, $siteId) {
            $data = $this->applyAvatarUpload($data, $avatarFile);

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }

            $data['site_id'] = $siteId;

            if (empty($data['seniority_date'])) {
                $data['seniority_date'] = null;
            }

            unset($data['total_published_articles'], $data['total_published_reviews']);

            return $this->authorRepository->create($data);
        });
    }

    public function updateAuthor(
        int $id,
        array $data,
        ?UploadedFile $avatarFile = null,
        ?int $adminId = null,
    ): Author
    {
        return $this->database->transaction(function () use ($id, $data, $avatarFile, $adminId) {
            $author = $this->authorRepository->find($id);

            if (!$author) {
                throw new Exception("Author not found");
            }

            $data = $this->applyAvatarUpload($data, $avatarFile, $author->avatar);

            // 7. Auto-regenerate slug if name changed and slug wasn't explicitly provided
            if (!empty($data['name']) && $data['name'] !== $author->name && empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }

            $updatedAuthor = $this->authorRepository->update($id, $data);

            if (!$updatedAuthor) {
                throw new Exception("Failed to update author");
            }

            if ($this->contributorAuthorSyncService) {
                $updatedAuthor = $this->contributorAuthorSyncService->recordAdminAuthorUpdate(
                    $updatedAuthor,
                    $data,
                    $adminId,
                );
            }

            return $updatedAuthor;
        });
    }

    public function getOverriddenFields(int $authorId): array
    {
        if (!$this->contributorAuthorSyncService) {
            $author = $this->authorRepository->find($authorId);

            if (!$author) {
                throw new Exception('Author not found');
            }

            return is_array($author->overridden_fields) ? $author->overridden_fields : [];
        }

        return $this->contributorAuthorSyncService->overriddenFields($authorId);
    }

    public function removeOverride(int $authorId, string $field, ?int $adminId = null): Author
    {
        if (!$this->contributorAuthorSyncService) {
            throw new Exception('Author synchronisation is not available');
        }

        return $this->contributorAuthorSyncService->removeOverride($authorId, $field, $adminId);
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

    public function searchAuthors(string $query, ?int $limit = null): Collection
    {
        return $this->authorRepository->searchAuthors($query, $limit);
    }

    /**
     * Persist an uploaded avatar path and ensure UploadedFile objects never
     * leak into the DB payload (validated() merges files into input).
     */
    private function applyAvatarUpload(
        array $data,
        ?UploadedFile $avatarFile = null,
        ?string $oldAvatarPath = null,
    ): array {
        $file = $avatarFile;

        // FormRequest::validated() merges $_FILES into the payload, so the
        // avatar may only exist as an UploadedFile inside $data.
        if (!$file && isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $file = $data['avatar'];
        }

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            unset($data['avatar']);
        }

        if ($file && $file->isValid()) {
            $data['avatar'] = $this->imageUploadService->upload($file, $oldAvatarPath);
        }

        return $data;
    }

    private function generateSlug(string $name): string
    {
        return Str::slug($name, [$this->authorRepository, 'findBySlug']);
    }
}
