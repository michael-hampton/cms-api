<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\MemberInsights\Newsletters\BuildNewsletterRelationshipsJob;
use App\Models\Site;

class BuildNewsletterRelationshipsCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;
    public $description = 'Builds or refreshes newsletter relationship graph for recommendation engine.';
    protected $signature = 'newsletters:build-relationships {site_id?}';

    public function handle(): int
    {
        $result = $this->createResult('newsletters:build-relationships');

        $sites = $this->argument('site_id')
            ? Site::where('id', (int)$this->argument('site_id'))->get()
            : Site::where('is_active', true)->get();

        if ($sites->isEmpty()) {
            $this->info('No active sites found.');
            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            try {
                dispatch(BuildNewsletterRelationshipsJob::for($site->id));

                $result->incrementSucceeded();
                $result->addMessage("Dispatched relationship builder for site #{$site->id} ({$site->name})");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to dispatch for site #{$site->id}: {$e->getMessage()}",
                    context: ['site_id' => $site->id],
                    throwable: $e,
                );
            }
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}