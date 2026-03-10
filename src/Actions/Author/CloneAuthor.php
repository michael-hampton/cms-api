<?php

namespace App\Actions\Author;

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
        return $this->database->transaction(function () use ($authorId, $newName) {
            $originalAuthor = $this->authorRepository->find($authorId);

            if (!$originalAuthor) {
                throw new \Exception("Author not found");
            }

            $results = ['success' => [], 'failed' => [], 'warnings' => []];

            $data = [
                'name' => $newName ?? ($originalAuthor->name . ' (Copy)'),
                // Email must be unique — clear it so the clone can be given a fresh one
                'email' => null,
                'bio' => $originalAuthor->bio,
                'site_id' => SiteContext::getId(),
                'website' => $originalAuthor->website,
                'twitter' => $originalAuthor->twitter,
                'linkedin' => $originalAuthor->linkedin,
                'facebook' => $originalAuthor->facebook,
                // Expertise / experience fields are cloned as-is
                'expertise' => $originalAuthor->expertise,
                'location' => $originalAuthor->location,
                'education' => $originalAuthor->education,
                'awards' => $originalAuthor->awards,
                'seniority_date' => $originalAuthor->seniority_date
                    ? $originalAuthor->seniority_date->format('Y-m-d')
                    : null,
                // Clones start inactive so they can be reviewed before publishing
                'is_active' => false,
                'status' => 'inactive',
            ];

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

            $originalAuthor->addCloneRecord('cloned_to', $newAuthor->id, null);
            $newAuthor->addCloneRecord('cloned_from', $originalAuthor->id, null);
            $results['success'][] = 'clone_history';

            return [
                'author' => $newAuthor,
                'results' => $results,
                'original_author_id' => $authorId,
            ];
        });
    }

    private function generateSlug(string $name): string
    {
        return Str::slug($name, [$this->authorRepository, 'findBySlug']);
    }
}