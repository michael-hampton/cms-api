<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Models\Category;
use App\Repositories\CategoryRepository;

class CloneCategory
{
    private Database $database;

    public function __construct(Database $database, private readonly CategoryRepository $repository)
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $categoryId, ?string $newName = null): bool
    {
        return $this->database->transaction(function() use ($categoryId, $newName) {
            $originalCategory = $this->repository->find($categoryId);

            if (!$originalCategory) {
                throw new \Exception("Category not found");
            }

            $data = [
                'name' => $newName ?? ($originalCategory->name . ' (Copy)'),
                'description' => $originalCategory->description,
                'parent_id' => $originalCategory->parent_id,
                'status' => 'inactive',
                'site_id' => $originalCategory->site_id,
                'seo_title' => $originalCategory->seo_title,
                'seo_description' => $originalCategory->seo_description,
                'no_index' => $originalCategory->no_index ?? false,
                'canonical_url' => null, // Don't copy canonical URL
            ];

            // Generate unique slug
            $baseName = $data['name'];
            $slug = Str::slug($baseName);
            $counter = 1;

            while ($this->repository->findBySlug($slug)) {
                $slug = Str::slug($baseName . '-' . $counter);
                $counter++;
            }

            $data['slug'] = $slug;

            $newCategory = $this->repository->create($data);

            if (!$newCategory) {
                throw new \Exception("Failed to create duplicate category");
            }

            // Add clone history
            $originalCategory->addCloneRecord('cloned_to', $newCategory->id, null);
            $newCategory->addCloneRecord('cloned_from', $originalCategory->id, null);

            // Duplicate child categories recursively
            $children = $originalCategory->children();
            foreach ($children as $child) {
                $this->duplicateCategoryRecursive($child, $newCategory->id);
            }

            return $newCategory !== null;
        });
    }

    private function duplicateCategoryRecursive(Category $category, int $newParentId): void
    {
        $data = [
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $newParentId,
            'status' => 'inactive',
            'site_id' => $category->site_id,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'no_index' => $category->no_index ?? false,
            'canonical_url' => null,
            'color' => $category->color,
            'icon' => $category->icon,
            'sort_order' => $category->sort_order,
            'is_active' => false,
        ];

        // Generate unique slug
        $baseName = $data['name'];
        $slug = Str::slug($baseName);
        $counter = 1;

        while ($this->repository->findBySlug($slug)) {
            $slug = Str::slug($baseName . '-' . $counter);
            $counter++;
        }

        $data['slug'] = $slug;

        $newCategory = $this->repository->create($data);

        // Recursively duplicate children
        $children = $category->children();
        foreach ($children as $child) {
            $this->duplicateCategoryRecursive($child, $newCategory->id);
        }
    }
}