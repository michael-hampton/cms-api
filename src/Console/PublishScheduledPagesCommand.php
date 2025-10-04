<?php

namespace App\Console;

use App\Jobs\PublishScheduledPagesJob;

class PublishScheduledPagesCommand
{
    private PublishScheduledPagesJob $job;

    public function __construct()
    {
        $this->job = new PublishScheduledPagesJob();
    }

    public function execute(): void
    {
        echo "Running scheduled page publisher...\n";

        $count = $this->job->handle();

        if ($count > 0) {
            echo "Successfully published {$count} page(s)\n";
        } else {
            echo "No pages to publish at this time\n";
        }
    }
}