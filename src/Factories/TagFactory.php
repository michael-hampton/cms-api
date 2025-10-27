<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Tag;

class TagFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Tag::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'slug' => 'test-tag-' . uniqid(),
            'name' => 'Test Tag',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function named(string $name): static
    {
        return $this->state([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        ]);
    }
}