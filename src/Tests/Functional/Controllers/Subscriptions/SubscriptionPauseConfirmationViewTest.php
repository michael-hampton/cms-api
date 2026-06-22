<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class SubscriptionPauseConfirmationViewTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_press_stack_renders_pause_subscription_modal_and_context_endpoint(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'delivery_type' => 'print',
            'auto_renew' => true,
            'includes_digital_access' => true,
        ]);
        $this->actingAsMember($member);

        $content = $this->get('/press-stack/account/subscriptions')->getContent();

        self::assertStringContainsString('Pause subscription', $content);
        self::assertStringContainsString('data-open-subscription-pause', $content);
        self::assertStringContainsString('id="subscription-pause-modal"', $content);
        self::assertStringContainsString(
            "\\/press-stack\\/account\\/subscriptions\\/{$subscription->id}\\/pause",
            $content,
        );
        self::assertStringContainsString('Pause print delivery', $content);
        self::assertStringNotContainsString(
            'data-account-action="api" data-endpoint="/press-stack/account/subscriptions/' . $subscription->id . '/pause"',
            $content,
        );
    }

    public function test_member_area_uses_site_scoped_pause_endpoint(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'delivery_type' => 'digital',
            'auto_renew' => false,
        ]);
        $this->actingAsMember($member);

        $content = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified')->getContent();

        self::assertStringContainsString('Pause subscription', $content);
        self::assertStringContainsString(
            "\\/{$this->siteSlug}\\/member\\/subscriptions\\/unified\\/{$subscription->id}\\/pause",
            $content,
        );
        self::assertStringContainsString('remain disabled when you resume', $content);
    }

    public function test_paused_subscription_displays_resume_without_pause_modal_trigger(): void
    {
        $member = $this->createMember();
        $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'paused',
            'auto_renew' => false,
        ]);
        $this->actingAsMember($member);

        $content = $this->get('/press-stack/account/subscriptions')->getContent();

        self::assertStringContainsString('Resume', $content);
        self::assertStringNotContainsString('data-open-subscription-pause', $content);
    }
}
