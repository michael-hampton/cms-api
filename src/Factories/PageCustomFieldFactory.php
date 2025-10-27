<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\PageCustomField;

class PageCustomFieldFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return PageCustomField::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'page_id' => null,
            'custom_field_definition_id' => null,
            'value' => 'Test value',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function forPage(int $pageId): static
    {
        return $this->state(['page_id' => $pageId]);
    }

    public function forDefinition(int $definitionId): static
    {
        return $this->state(['custom_field_definition_id' => $definitionId]);
    }

    public function withValue(string $value): static
    {
        return $this->state(['value' => $value]);
    }
}