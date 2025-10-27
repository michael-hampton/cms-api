<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Category;

class CategoryFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Category::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'parent_id' => null,
            'slug' => 'test-category-' . uniqid(),
            'name' => 'Test Category',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function withParent(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }

    public function named(string $name): static
    {
        return $this->state([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        ]);
    }
}