<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\Block;

class BlockFactory extends Factory
{
    protected function model(): string
    {
        return Block::class;
    }

    protected function definition(): array
    {
        return [
            'page_id' => null,
            'type' => 'text',
            'data' => json_encode(['content' => 'Test content']),
            'order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Set the parent page
     */
    public function forPage(int $pageId): static
    {
        return $this->state(['page_id' => $pageId]);
    }

    /**
     * Custom state: text block
     */
    public function text(string $content = 'Test content'): static
    {
        return $this->state([
            'type' => 'text',
            'data' => json_encode(['content' => $content]),
        ]);
    }

    /**
     * Custom state: image block
     */
    public function image(string $url = 'https://example.com/image.jpg'): static
    {
        return $this->state([
            'type' => 'image',
            'data' => json_encode(['url' => $url]),
        ]);
    }
}