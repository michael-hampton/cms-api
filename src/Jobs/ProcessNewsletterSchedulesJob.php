<?php

namespace App\Jobs;

use App\Framework\Support\Logger;
use App\Services\Newsletter\NewsletterSendScheduleRunner;

/**
 * Processes all newsletter send schedules that are currently due.
 *
 * Designed to be invoked by the Laravel scheduler on a regular cadence
 * (e.g. every minute).  A ShouldBeUnique constraint prevents a second job
 * from overlapping if the previous run is still in progress.
 *
 * Registration in app/Console/Kernel.php:
 *
 *   $schedule->job(ProcessNewsletterSchedulesJob::class)
 *            ->everyMinute()
 *            ->withoutOverlapping();
 *
 * The job accepts an optional $siteId to scope the run to a single site,
 * which is useful for multi-tenant setups or manual site-scoped triggers.
 */
class ProcessNewsletterSchedulesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is marked as failed.
     * Newsletter delivery errors are caught inside the runner and logged,
     * so a single attempt is sufficient — retries would re-process already
     * advanced schedules.
     */
    public int $tries = 1;

    /**
     * Number of seconds the job may run before timing out.
     * Set generously to accommodate large recipient lists.
     */
    public int $timeout = 300;

    public function __construct(
        private readonly ?int $siteId = null,
    )
    {
    }

    public function handle(
        NewsletterSendScheduleRunner $runner,
        Logger                       $logger,
    ): void
    {
        $context = $this->siteId !== null
            ? ['site_id' => $this->siteId]
            : ['site_id' => 'all'];

        $logger->info('ProcessNewsletterSchedulesJob started', $context);

        $result = $runner->run($this->siteId);

        $logger->info('ProcessNewsletterSchedulesJob completed', array_merge($context, $result));
    }

    /**
     * The unique ID ensures only one global instance of this job runs at a time
     * (or one per site when $siteId is provided).
     */
    public function uniqueId(): string
    {
        return $this->siteId !== null
            ? 'newsletter-schedules-site-' . $this->siteId
            : 'newsletter-schedules-all';
    }

    /**
     * Number of seconds to keep the uniqueness lock.
     * Matches the timeout so a timed-out job does not block the next run
     * indefinitely.
     */
    public function uniqueFor(): int
    {
        return $this->timeout;
    }
}