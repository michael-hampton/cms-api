<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Territory;

class TerritoryFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Territory::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'code' => 'TEST-' . uniqid(),
            'region_set_id' => null,
            'name' => 'Test Territory',
            'slug' => 'test-territory-' . uniqid(),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function forRegionSet(int $regionSetId): static
    {
        return $this->state(['region_set_id' => $regionSetId]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withCode(string $code): static
    {
        return $this->state(['code' => $code]);
    }
}