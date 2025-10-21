<?php

namespace App\Tests\Unit\Models;

use App\Models\Newsletter;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterModelTest extends FunctionalTestCase
{
    public function testShouldSendDailyNewsletter(): void
    {
        $newsletter = new Newsletter([
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => true
        ]);

        $this->assertTrue($newsletter->shouldSend());
    }

    public function testShouldNotSendRecentNewsletter(): void
    {
        $newsletter = new Newsletter([
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s'),
            'active' => true
        ]);

        $this->assertFalse($newsletter->shouldSend());
    }

    public function testShouldNotSendInactiveNewsletter(): void
    {
        $newsletter = new Newsletter([
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => false
        ]);

        $this->assertFalse($newsletter->shouldSend());
    }

    public function testShouldSendNewsletterWithoutLastSent(): void
    {
        $newsletter = new Newsletter([
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => null,
            'active' => true
        ]);

        $this->assertTrue($newsletter->shouldSend());
    }

    public function testWeeklyNewsletterInterval(): void
    {
        $newsletter = new Newsletter([
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-8 days')),
            'active' => true
        ]);

        $this->assertTrue($newsletter->shouldSend());

        $newsletter->last_sent = date('Y-m-d H:i:s', strtotime('-5 days'));
        $this->assertFalse($newsletter->shouldSend());
    }

    public function testMonthlyNewsletterInterval(): void
    {
        $newsletter = new Newsletter([
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-31 days')),
            'active' => true
        ]);

        $this->assertTrue($newsletter->shouldSend());

        $newsletter->last_sent = date('Y-m-d H:i:s', strtotime('-15 days'));
        $this->assertFalse($newsletter->shouldSend());
    }
}