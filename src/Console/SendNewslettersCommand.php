<?php

namespace App\Console;

use App\Models\Site;
use App\Parsers\BlockParserService;
use App\Parsers\EmailService;
use App\Parsers\NewsletterSendService;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\SubscriberRepository;

class SendNewslettersCommand
{
    public function handle(): void
    {
        echo "Checking for newsletters to send...\n";

        $sites = Site::all();

        foreach ($sites as $siteData) {
            $site = new Site($siteData);
            echo "Processing site: {$site->name} (ID: {$site->id})\n";

            // Get dependencies - adjust based on your DI container
            $parser = new BlockParserService(/* inject dependencies */);
            $emailService = new EmailService();
            $subscriberRepo = new SubscriberRepository();
            $newsletterRepo = new NewsletterRepository();
            $sendRepo = new NewsletterSendRepository();

            $service = new NewsletterSendService(
                $parser,
                $emailService,
                $subscriberRepo,
                $newsletterRepo,
                $sendRepo,
                $site->id
            );

            $results = $service->sendDueNewsletters();

            foreach ($results as $result) {
                if ($result['success']) {
                    echo "  Sent newsletter {$result['newsletter_id']} to {$result['recipients']} recipients\n";
                } else {
                    echo "  Failed to send newsletter {$result['newsletter_id']}: {$result['error']}\n";
                }
            }
        }

        echo "Done!\n";
    }
}