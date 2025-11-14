<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Repositories\AuthorRepository;
use App\Services\ImageUploadService;

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
    public function handle(int $authorId, ?string $newName = null): Author
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

            $newAuthor = $this->authorRepository->create($data);

            // Add clone history
            $originalAuthor->addCloneRecord('cloned_to', $newAuthor->id, null);
            $newAuthor->addCloneRecord('cloned_from', $originalAuthor->id, null);

            return $newAuthor;
        });
    }

    private function generateSlug(string $name): string
    {
        return Str::slug($name, [$this->authorRepository, 'findBySlug']);
    }
}