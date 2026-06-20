<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class UnifiedSubscriptionAcquisitionTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_member_modal_contains_only_active_current_site_plans(): void
    {
        $member = $this->createMember();
        $otherSite = Site::create([
            'name' => 'Other Publication',
            'slug' => 'other-publication-' . uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'name' => 'Visible Current Plan',
            'slug' => 'visible-current-plan',
            'is_active' => true,
        ]);
        $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'name' => 'Inactive Current Plan',
            'slug' => 'inactive-current-plan',
            'is_active' => false,
        ]);
        $this->createSubscriptionPlan([
            'site_id' => $otherSite->id,
            'name' => 'Other Site Plan',
            'slug' => 'other-site-plan',
            'is_active' => true,
        ]);
        $this->actingAsMember($member);

        $content = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified')->getContent();

        self::assertStringContainsString('Visible Current Plan', $content);
        self::assertStringNotContainsString('Inactive Current Plan', $content);
        self::assertStringNotContainsString('Other Site Plan', $content);
    }

    public function test_press_stack_does_not_render_acquisition_plans_or_scripts(): void
    {
        $member = $this->createMember();
        $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'name' => 'PressStack Hidden Plan',
            'slug' => 'press-stack-hidden-plan',
            'is_active' => true,
        ]);
        $this->actingAsMember($member);

        $content = $this->get('/press-stack/account/subscriptions')->getContent();

        self::assertStringNotContainsString('PressStack Hidden Plan', $content);
        self::assertStringNotContainsString('id="subscriptionModal"', $content);
        self::assertStringNotContainsString('subscription-account-acquisition.js', $content);
    }
}
