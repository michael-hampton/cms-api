<?php

namespace App\Factories;

use App\Framework\Support\Str;
use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Page;

class PageFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Page::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'slug' => 'test-page-' . uniqid(),
            'title' => 'Test Page',
            'subtitle' => 'Test content',
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Custom state: published page
     */
    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }

    /**
     * Custom state: draft page
     */
    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    /**
     * Custom state: with specific title
     */
    public function titled(string $title): static
    {
        return $this->state([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . uniqid(),
        ]);
    }
}