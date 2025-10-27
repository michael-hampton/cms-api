<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\PageSeo;

class PageSeoFactory extends Factory
{
    protected function model(): string
    {
        return PageSeo::class;
    }

    protected function definition(): array
    {
        return [
            'page_id' => null,
            'meta_title' => 'Test SEO Title',
            'meta_description' => 'Test SEO Description',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forPage(int $pageId): static
    {
        return $this->state(['page_id' => $pageId]);
    }

    public function withTitle(string $title): static
    {
        return $this->state(['meta_title' => $title]);
    }
}