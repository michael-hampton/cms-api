<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Repositories\Cms\AuthorRepository;
use App\Services\Cms\ImageUploadService;

class CloneAuthor
{
    private Database $database;

    public function __construct(
        private readonly AuthorRepository   $authorRepository,
        private readonly ImageUploadService $imageUploadService,
        ?Database                           $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $authorId, ?string $newName = null): array
    {
        return $this->database->transaction(function() use ($authorId, $newName) {
            $originalAuthor = $this->authorRepository->find($authorId);

            if (!$originalAuthor) {
                throw new \Exception("Author not found");
            }

            $results = ['success' => [], 'failed' => [], 'warnings' => []];

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
                    $results['success'][] = 'avatar';
                } catch (\Exception $e) {
                    $data['avatar'] = null;
                    $results['failed'][] = ['field' => 'avatar', 'error' => $e->getMessage()];
                }
            }

            $newAuthor = $this->authorRepository->create($data);
            $results['success'][] = 'author_created';

            // Add clone history
            $originalAuthor->addCloneRecord('cloned_to', $newAuthor->id, null);
            $newAuthor->addCloneRecord('cloned_from', $originalAuthor->id, null);
            $results['success'][] = 'clone_history';

            return [
                'author' => $newAuthor,
                'results' => $results,
                'original_author_id' => $authorId
            ];
        });
    }

    private function generateSlug(string $name): string
    {
        return Str::slug($name, [$this->authorRepository, 'findBySlug']);
    }
}