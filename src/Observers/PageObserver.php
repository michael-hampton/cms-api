<?php

namespace App\Observers;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Observers\Observer;
use App\Framework\Support\Cache;
use App\Framework\Support\Event;
use App\Framework\Support\Logger;
use App\Models\Block;
use App\Models\Model;
use App\Models\Page;

class PageObserver extends Observer
{
    public function creating(Model $page): void //too had to change type
    {
        // Auto-generate slug if not provided
        if (empty($page->slug) && !empty($page->title)) {
            $page->slug = $this->generateSlug($page->title);
        }

        Logger::info('Creating new page', ['title' => $page->title]);
    }

    public function created(Model $page): void
    {
        // Fire event for other systems
        Event::fire('page.created', ['page' => $page->toArray()]);

        // Clear cache
        Cache::forget('published_pages');

        Logger::info('Page created successfully', ['page_id' => $page->id]);
    }

    public function updating(Model $page): void
    {
        // Log what's being changed
        //$changes = array_diff_assoc($page->attributes, $page->original);
        //Logger::info('Updating page', ['page_id' => $page->id, 'changes' => $changes]);
    }

    public function updated(Model $page): void
    {
        // Clear relevant caches
        Cache::forget('published_pages');
        Cache::forget("page_{$page->id}");

        Event::fire('page.updated', ['page' => $page->toArray()]);
    }

    public function deleting(Model $page): void
    {
        // Log deletion attempt
        //Logger::info('Attempting to delete page', ['page_id' => $page->id]);

        // You could prevent deletion here by returning false
        if ($page->status === 'published') {
            //Logger::warn('Attempting to delete published page', ['page_id' => $page->id]);
        }
    }

    public function deleted(Model $page): void
    {
        // Clean up related data
        $this->cleanupRelatedData($page);

        // Clear caches
        Cache::forget('published_pages');
        Cache::forget("page_{$page->id}");

        Event::fire('page.deleted', ['page_id' => $page->id]);
        Logger::info('Page deleted', ['page_id' => $page->id]);
    }

    private function generateSlug(?string $title): string
    {
        if (empty($title)) {
            return '';
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

        // Check for uniqueness
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $database = Database::getInstance();
        $query = new QueryBuilder(
            'pages',
            new EagerLoader(
                new RelationshipAnalyzer(),
                new RelationHandlerFactory($database)
            ),
            $database
        );
        return $query->where('slug', $slug)->exists();
    }

    private function cleanupRelatedData(Page $page): void
    {
        // Delete associated blocks
//        Block::where('page_id', $page->id)->each(function (Block $block) {
//            $block->delete();
//        });

        // Clean up any uploaded files associated with this page
        // This would depend on your file storage implementation
    }
}