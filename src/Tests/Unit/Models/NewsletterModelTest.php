<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Newsletter;
use App\Models\ProductOfferBundleRegionSet;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterModelTest extends FunctionalTestCase
{
    use CreatesTestData;
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

    public function testNewsletterWithNoRegionSetsIsVisibleToAnyMember(): void
    {
        $newsletter = $this->createNewsletter();
        $member = new Member(['territory_id' => 99]);

        $newsletter->setRelation('regionSets', new \App\Framework\Support\Collection([]));

        $this->assertTrue($newsletter->isVisibleToMember($member));
    }

    public function testNewsletterIsVisibleToMemberWithMatchingTerritory(): void
    {
        $newsletter = $this->createNewsletter();
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $newsletter->id,
            'region_set_id' => $regionSet->id,
        ]);

        $member = $this->createMember(['territory_id' => $territory->id]);

        $this->assertTrue($newsletter->isVisibleToMember($member));
    }

    public function testNewsletterIsNotVisibleToMemberWithNonMatchingTerritory(): void
    {
        $newsletter = $this->createNewsletter();
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $newsletter->id,
            'region_set_id' => $regionSet->id,
        ]);

        $territory2 = $this->createTerritory();
        $member = $this->createMember(['territory_id' => $territory2->id]);

        $this->assertFalse($newsletter->isVisibleToMember($member));
    }

    public function testNewsletterIsVisibleToNullMember(): void
    {
        $newsletter = $this->createNewsletter();

        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $newsletter->id,
            'region_set_id' => $this->createRegionSet()->id,
        ]);

        $this->assertTrue($newsletter->isVisibleToMember(null));
    }

    public function testNewsletterIsVisibleToMemberWithNoTerritory(): void
    {
        $newsletter = $this->createNewsletter();
        $regionSet = $this->createRegionSet();

        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $newsletter->id,
            'region_set_id' => $regionSet->id,
        ]);

        $member = new Member(); // no territory_id

        $this->assertTrue($newsletter->isVisibleToMember($member));
    }

    public function testScopeVisibleToMemberFiltersRestrictedNewsletters(): void
    {
        $open = $this->createNewsletter(['slug' => 'open-' . uniqid()]);
        $restricted = $this->createNewsletter(['slug' => 'restricted-' . uniqid()]);

        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $restricted->id,
            'region_set_id' => $regionSet->id,
        ]);

        $otherTerritory = $this->createTerritory();
        $member = $this->createMember(['territory_id' => $otherTerritory->id]);

        $results = Newsletter::where('site_id', $this->siteId)
            ->visibleToMember($member)
            ->get();

        $ids = $results->pluck('id')->toArray();

        $this->assertContains($open->id, $ids);
        $this->assertNotContains($restricted->id, $ids);
    }

    public function testScopeVisibleToMemberShowsAllForNullMember(): void
    {
        $open = $this->createNewsletter(['slug' => 'open2-' . uniqid()]);
        $restricted = $this->createNewsletter(['slug' => 'restricted2-' . uniqid()]);

        $regionSet = $this->createRegionSet();
        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $restricted->id,
            'region_set_id' => $regionSet->id,
        ]);

        $results = Newsletter::where('site_id', $this->siteId)
            ->visibleToMember(null)
            ->get();

        $ids = $results->pluck('id')->toArray();

        $this->assertContains($open->id, $ids);
        $this->assertContains($restricted->id, $ids);
    }

    public function testRegionSetsRelationship(): void
    {
        $newsletter = $this->createNewsletter();
        $regionSet = $this->createRegionSet();

        ProductOfferBundleRegionSet::create([
            'newsletter_id' => $newsletter->id,
            'region_set_id' => $regionSet->id,
        ]);

        $newsletter->load(['regionSets']);

        $this->assertCount(1, $newsletter->regionSets);
        $this->assertEquals($regionSet->id, $newsletter->regionSets->first()->id);
    }
}