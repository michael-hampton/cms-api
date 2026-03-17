<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\PublishScheduledPagesJob;

class PublishScheduledPagesCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'cms:publish-scheduled';
    public $description = 'Publishes pages that are scheduled for the current time.';

    public function handle(): int
    {
        $result = $this->createResult('cms:publish-scheduled');
        $job = new PublishScheduledPagesJob();

        try {
            $count = $job->handle();

            if ($count > 0) {
                $result->incrementSucceeded();
                $result->addMessage("Successfully published {$count} page(s).");
            } else {
                $result->addMessage("No pages to publish at this time.");
            }
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "Failed to publish scheduled pages: {$e->getMessage()}",
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}