<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\PageMetadata;

class PageMetadataFactory extends Factory
{
    protected function model(): string
    {
        return PageMetadata::class;
    }

    protected function definition(): array
    {
        return [
            'page_id' => null,
            'featured' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forPage(int $pageId): static
    {
        return $this->state(['page_id' => $pageId]);
    }

    public function featured(): static
    {
        return $this->state(['featured' => 1]);
    }
}