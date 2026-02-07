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

    public function test_has_geographic_restrictions(): void
    {
        $newsletter = new Newsletter(['has_geographic_restrictions' => true]);
        $this->assertTrue($newsletter->hasGeographicRestrictions());

        $newsletter = new Newsletter(['has_geographic_restrictions' => false]);
        $this->assertFalse($newsletter->hasGeographicRestrictions());

        $newsletter = new Newsletter();
        $this->assertFalse($newsletter->hasGeographicRestrictions());
    }

    public function test_is_region_allowed_without_restrictions(): void
    {
        $newsletter = new Newsletter(['has_geographic_restrictions' => false]);
        $this->assertTrue($newsletter->isRegionAllowed('US'));
        $this->assertTrue($newsletter->isRegionAllowed(null));
    }

    public function test_is_region_allowed_with_allowlist(): void
    {
        $newsletter = new Newsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA']
        ]);

        $this->assertTrue($newsletter->isRegionAllowed('US'));
        $this->assertTrue($newsletter->isRegionAllowed('CA'));
        $this->assertFalse($newsletter->isRegionAllowed('GB'));
        $this->assertFalse($newsletter->isRegionAllowed(null));
    }

    public function test_is_region_allowed_with_blocklist(): void
    {
        $newsletter = new Newsletter([
            'has_geographic_restrictions' => true,
            'blocked_regions' => ['CN', 'RU']
        ]);

        $this->assertTrue($newsletter->isRegionAllowed('US'));
        $this->assertFalse($newsletter->isRegionAllowed('CN'));
        $this->assertFalse($newsletter->isRegionAllowed('RU'));
    }

    public function test_is_region_allowed_blocklist_takes_precedence(): void
    {
        $newsletter = new Newsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA'],
            'blocked_regions' => ['CA']
        ]);

        $this->assertTrue($newsletter->isRegionAllowed('US'));
        $this->assertFalse($newsletter->isRegionAllowed('CA'));
    }

    public function test_has_time_window(): void
    {
        $newsletter = new Newsletter(['has_time_window' => true]);
        $this->assertTrue($newsletter->hasTimeWindow());

        $newsletter = new Newsletter(['has_time_window' => false]);
        $this->assertFalse($newsletter->hasTimeWindow());
    }

    public function test_is_within_access_window(): void
    {
        $start = new \DateTime('-1 hour');
        $end = new \DateTime('+1 hour');

        $newsletter = new Newsletter([
            'has_time_window' => true,
            'access_window_start' => $start,
            'access_window_end' => $end
        ]);

        $this->assertTrue($newsletter->isWithinAccessWindow(new \DateTime()));
        $this->assertFalse($newsletter->isWithinAccessWindow(new \DateTime('-2 hours')));
        $this->assertFalse($newsletter->isWithinAccessWindow(new \DateTime('+2 hours')));
    }

    public function test_is_within_access_window_no_window(): void
    {
        $newsletter = new Newsletter(['has_time_window' => false]);
        $this->assertTrue($newsletter->isWithinAccessWindow(new \DateTime()));
    }

    public function test_requires_bundle(): void
    {
        $newsletter = new Newsletter(['requires_bundle' => true]);
        $this->assertTrue($newsletter->requiresBundle());

        $newsletter = new Newsletter(['requires_bundle' => false]);
        $this->assertFalse($newsletter->requiresBundle());
    }
}