<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class UnifiedSubscriptionAccountViewTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_press_stack_renders_shared_account_without_acquisition(): void
    {
        $member = $this->createMember();
        $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'plan_name' => 'Global Digital',
        ]);
        $this->actingAsMember($member);

        $response = $this->get('/press-stack/account/subscriptions');

        $this->assertResponseStatus(200, $response);
        $content = $response->getContent();
        self::assertStringContainsString('subscription-account--press_stack', $content);
        self::assertStringContainsString('id="subscription-manage-drawer"', $content);
        self::assertStringNotContainsString('data-open-subscription-modal', $content);
        self::assertStringNotContainsString('id="subscriptionModal"', $content);
    }

    public function test_member_comparison_page_renders_shared_account_with_acquisition(): void
    {
        $member = $this->createMember();
        $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'is_active' => true,
        ]);
        $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'plan_name' => 'Site Print',
            'delivery_type' => 'print',
        ]);
        $this->actingAsMember($member);

        $response = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified');

        $this->assertResponseStatus(200, $response);
        $content = $response->getContent();
        self::assertStringContainsString('subscription-account--member', $content);
        self::assertStringContainsString('data-open-subscription-modal', $content);
        self::assertStringContainsString('id="subscriptionModal"', $content);
        self::assertStringContainsString('/public/css/member-subscription-account.css', $content);
        self::assertStringContainsString('/public/js/subscription-account-acquisition.js', $content);
        self::assertStringContainsString('Site Print', $content);
        self::assertStringContainsString('/' . $this->siteSlug . '/member/subscriptions/unified/', $content);
    }

    public function test_unified_management_route_rejects_subscription_from_another_site(): void
    {
        $member = $this->createMember();
        $otherSite = Site::create([
            'name' => 'Other Publication',
            'slug' => 'other-publication-' . uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $otherSite->id,
            'status' => 'active',
        ]);
        $this->actingAsMember($member);

        $response = $this->get(
            '/' . $this->siteSlug . '/member/subscriptions/unified/' . $subscription->id . '/history',
        );

        $this->assertResponseStatus(404, $response);
    }
}
