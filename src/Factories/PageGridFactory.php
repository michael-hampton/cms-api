<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Author;
use App\Models\PageGrid;

class PageGridFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return PageGrid::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'items' => []
        ]);
    }

    public function named(string $name): static
    {
        return $this->state([
            'title' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        ]);
    }
}