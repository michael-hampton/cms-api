<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\PageHistory;

class PageHistoryFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return PageHistory::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'page_id' => null,
            'user_id' => null,
            'action' => 'created',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function forPage(int $pageId): static
    {
        return $this->state(['page_id' => $pageId]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(['user_id' => $userId]);
    }

    public function withAction(string $action): static
    {
        return $this->state(['action' => $action]);
    }
}