<?php

namespace App\Observers;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Observers\Observer;
use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Event;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;

class PageObserver extends Observer
{
    public function creating(Model $page): void
    {
        if (empty($page->slug) && !empty($page->title)) {
            $page->slug = $this->generateSlug($page->title);
        }

        Logger::info('Creating new page', ['title' => $page->title]);
    }

    public function created(Model $page): void
    {
        Event::fire('page.created', ['page' => $page->toArray()]);
        $this->forgetPageCaches($page);
        Logger::info('Page created successfully', ['page_id' => $page->id]);
    }

    public function updating(Model $page): void
    {
    }

    public function updated(Model $page): void
    {
        $this->forgetPageCaches($page);
        Event::fire('page.updated', ['page' => $page->toArray()]);
    }

    public function deleting(Model $page): void
    {
    }

    public function deleted(Model $page): void
    {
        $this->cleanupRelatedData($page);
        $this->forgetPageCaches($page);
        Event::fire('page.deleted', ['page_id' => $page->id]);
        Logger::info('Page deleted', ['page_id' => $page->id]);
    }

    private function forgetPageCaches(Model $page): void
    {
        Cache::forget('published_pages');
        Cache::forget("page_{$page->id}");

        PublicContentPageRepository::forgetPage(
            (int) $page->id,
            (int) $page->site_id,
            (string) $page->slug,
        );
    }

    private function generateSlug(?string $title): string
    {
        if (empty($title)) {
            return '';
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
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
                new RelationHandlerFactory($database),
            ),
            $database,
        );

        return $query->where('slug', $slug)->exists();
    }

    private function cleanupRelatedData(Page $page): void
    {
    }
}
