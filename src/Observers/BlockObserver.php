<?php

namespace App\Observers;

use App\Framework\Observers\Observer;
use App\Framework\Support\Cache;
use App\Framework\Support\Event;
use App\Framework\Support\Logger;
use App\Models\Block;
use App\Models\Model;

class BlockObserver extends Observer
{
    public function creating(Model $block): void
    {
        // Validate block data structure
        if (!$this->isValidBlockData($block)) {
            Logger::error('Invalid block data structure', [
                'block_type' => $block->type,
                'page_id' => $block->page_id
            ]);
        }
    }

    public function created(Model $block): void
    {
        // Clear page cache when blocks are added
        Cache::forget("page_blocks_{$block->page_id}");

        Event::fire('block.created', ['block' => $block->toArray()]);
    }

    public function updated(Model $block): void
    {
        // Clear page cache when blocks are updated
        Cache::forget("page_blocks_{$block->page_id}");

        Event::fire('block.updated', ['block' => $block->toArray()]);
    }

    public function deleted(Model $block): void
    {
        // Clean up any files associated with this block
        $this->cleanupBlockFiles($block);

        // Clear page cache
        Cache::forget("page_blocks_{$block->page_id}");

        Event::fire('block.deleted', ['block_id' => $block->id]);
    }

    private function isValidBlockData(Model $block): bool
    {
        $data = is_string($block->data) ? json_decode($block->data, true) : $block->data;

        // Basic validation - you can extend this
        if (!is_array($data)) {
            return false;
        }

        // Type-specific validation
        switch ($block->type) {
            case 'image':
                return isset($data['url']) && filter_var($data['url'], FILTER_VALIDATE_URL);
            case 'text':
                return isset($data['content']) && !empty($data['content']);
            default:
                return true;
        }
    }

    private function cleanupBlockFiles(Model $block): void
    {
        $data = is_string($block->data) ? json_decode($block->data, true) : $block->data;

        // Clean up files based on block type
        if ($block->type === 'image' && isset($data['url'])) {
            $filePath = $data['url'];
            if (file_exists($filePath)) {
                unlink($filePath);
                Logger::info('Deleted block file', ['file' => $filePath]);
            }
        }
    }
}