<?php

namespace App\Observers;

use App\Framework\Observers\Observer;
use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Event;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;

class BlockObserver extends Observer
{
    public function creating(Model $block): void
    {
        if (!$this->isValidBlockData($block)) {
            Logger::error('Invalid block data structure', [
                'block_type' => $block->type,
                'page_id' => $block->page_id,
            ]);
        }
    }

    public function created(Model $block): void
    {
        $this->forgetPageCaches((int) $block->page_id);
        Event::fire('block.created', ['block' => $block->toArray()]);
    }

    public function updated(Model $block): void
    {
        $this->forgetPageCaches((int) $block->page_id);
        Event::fire('block.updated', ['block' => $block->toArray()]);
    }

    public function deleted(Model $block): void
    {
        $this->cleanupBlockFiles($block);
        $this->forgetPageCaches((int) $block->page_id);
        Event::fire('block.deleted', ['block_id' => $block->id]);
    }

    private function forgetPageCaches(int $pageId): void
    {
        Cache::forget("page_blocks_{$pageId}");

        $page = Page::find($pageId);
        if ($page) {
            PublicContentPageRepository::forgetPage(
                $pageId,
                (int) $page->site_id,
                (string) $page->slug,
            );
        }
    }

    private function isValidBlockData(Model $block): bool
    {
        $data = is_string($block->data) ? json_decode($block->data, true) : $block->data;
        if (!is_array($data)) {
            return false;
        }

        switch ($block->type) {
            case 'image':
                $url = $data['url'] ?? $data['src'] ?? $data['image_url'] ?? null;
                return isset($data['image_id'])
                    || isset($data['cms_image_id'])
                    || ($url !== null && filter_var($url, FILTER_VALIDATE_URL));
            case 'text':
                return isset($data['content']) && !empty($data['content']);
            default:
                return true;
        }
    }

    private function cleanupBlockFiles(Model $block): void
    {
        $data = is_string($block->data) ? json_decode($block->data, true) : $block->data;
        $url = is_array($data) ? ($data['url'] ?? $data['src'] ?? $data['image_url'] ?? null) : null;

        if ($block->type === 'image' && is_string($url) && file_exists($url)) {
            unlink($url);
            Logger::info('Deleted block file', ['file' => $url]);
        }
    }
}
