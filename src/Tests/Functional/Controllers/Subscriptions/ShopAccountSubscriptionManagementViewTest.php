<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class ShopAccountSubscriptionManagementViewTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_manage_action_opens_in_page_drawer_for_current_subscription(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'status' => 'active',
            'auto_renew' => false,
            'plan_name' => 'Daily Digital',
        ]);
        $this->actingAsMember($member);

        $response = $this->get('/press-stack/account/subscriptions');

        $this->assertResponseStatus(200, $response);
        $content = $response->getContent();

        self::assertStringContainsString('id="subscription-manage-drawer"', $content);
        self::assertStringContainsString('class="subscription-drawer"', $content);
        self::assertStringContainsString('/public/css/subscription-account-drawer.css', $content);
        self::assertStringContainsString('data-open-subscription-manage', $content);
        self::assertStringContainsString(
            "\\/press-stack\\/account\\/subscriptions\\/{$subscription->id}\\/auto-renew",
            $content,
        );
        self::assertStringNotContainsString(
            "/@this->siteSlug/member/subscriptions/unified",
            $content,
        );
    }

    public function test_previous_subscription_cannot_manage_auto_renew(): void
    {
        $member = $this->createMember();
        $this->createSubscription([
            'member_id' => $member->id,
            'status' => 'expired',
            'auto_renew' => false,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->actingAsMember($member);

        $response = $this->get('/press-stack/account/subscriptions');

        $this->assertResponseStatus(200, $response);
        self::assertStringContainsString(
            '&quot;can_manage_auto_renew&quot;:false',
            $response->getContent(),
        );
    }
}
