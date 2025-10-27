<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\RegionSet;

class RegionSetFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return RegionSet::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set-' . uniqid(),
            'is_active' => true,
            'sort_order' => 0,
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