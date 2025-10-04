<?php

namespace App\Jobs;

use App\Models\Page;
use DateTime;

class PublishScheduledPagesJob
{
    public function handle(): int
    {
        $publishedCount = 0;
        $now = new DateTime();

        // Find all draft pages with metadata that have a publish_date in the past
        $pages = Page::where('status', 'draft')
            ->join('page_metadata', 'pages.id', '=', 'page_metadata.page_id')
            ->where('page_metadata.publish_date', '<=', $now->format('Y-m-d H:i:s'))
            ->select('pages.*')
            ->get();

        foreach ($pages as $page) {
            $page->status = 'published';
            $page->published_at = $now->format('Y-m-d H:i:s');
            $page->save();

            $publishedCount++;

            // Log the publishing
            error_log("Published page ID {$page->id}: {$page->title}");
        }

        return $publishedCount;
    }

    /**
     * Run this job via cron
     * Add to crontab: * * * * * php /path/to/artisan schedule:run
     */
    public static function schedule(): void
    {
        // Run every minute
        // In your scheduler/cron system, call:
        // $job = new PublishScheduledPagesJob();
        // $job->handle();
    }
}