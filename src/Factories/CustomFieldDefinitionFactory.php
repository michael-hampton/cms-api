<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\CustomFieldDefinition;

class CustomFieldDefinitionFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return CustomFieldDefinition::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'key' => 'test_field_' . uniqid(),
            'name' => 'Test Field',
            'type' => 'text',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function withKey(string $key): static
    {
        return $this->state(['key' => $key]);
    }
}