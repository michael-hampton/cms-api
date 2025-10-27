<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Brand;

class BrandFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Brand::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'slug' => 'test-brand-' . uniqid(),
            'name' => 'Test Brand',
            'description' => 'Test description',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function named(string $name): static
    {
        return $this->state([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        ]);
    }
}