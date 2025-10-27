<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\PageSettings;

class PageSettingsFactory extends Factory
{
    protected function model(): string
    {
        return PageSettings::class;
    }

    protected function definition(): array
    {
        return [
            'page_id' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forPage(int $pageId): static
    {
        return $this->state(['page_id' => $pageId]);
    }
}