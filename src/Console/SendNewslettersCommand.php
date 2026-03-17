<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\Site;
use App\Services\Newsletter\NewsletterSendService;

class SendNewslettersCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'newsletters:send';
    public $description = 'Processes and sends due newsletters for all sites.';

    public function handle(): int
    {
        $result = $this->createResult('newsletters:send');
        $sites = Site::all();

        foreach ($sites as $site) {

            try {
                // Dependency instantiation (consider moving to constructor if DI is available)
                $service = app(NewsletterSendService::class);

                $sendResults = $service->sendDueNewsletters();

                foreach ($sendResults as $sendResult) {
                    if ($sendResult['success']) {
                        $result->incrementSucceeded();
                        $result->addMessage("Site #{$site->id}: Sent newsletter {$sendResult['newsletter_id']} to {$sendResult['recipients']} recipients");
                    } else {
                        $result->addMessage("Site #{$site->id}: Failed newsletter {$sendResult['newsletter_id']} - {$sendResult['error']}");
                    }
                }
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Critical failure processing site #{$site->id}: {$e->getMessage()}",
                    context: ['site_id' => $site->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}