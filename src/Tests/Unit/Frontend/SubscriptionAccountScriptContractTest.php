<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class SubscriptionAccountScriptContractTest extends TestCase
{
    public function test_manage_drawer_partial_contains_markup_only(): void
    {
        $source = $this->read('views/subscriptions/account/_subscription_manage_drawer.php');

        self::assertStringNotContainsString(
            'subscription-account-drawer-bootstrap.js',
            $source,
        );
    }

    public function test_each_account_wrapper_loads_drawer_bootstrap_once(): void
    {
        foreach ([
            'views/subscriptions/account/subscriptions.php',
            'views/member/subscriptions/unified.php',
        ] as $path) {
            $source = $this->read($path);

            self::assertSame(
                1,
                substr_count($source, 'subscription-account-drawer-bootstrap.js'),
                $path,
            );
        }
    }

    public function test_shared_view_loads_the_controller_covered_by_pause_tests(): void
    {
        $source = $this->read('views/subscriptions/shared/_subscription_account.php');

        self::assertStringContainsString(
            'subscription-account-pause-controller.js',
            $source,
        );
        self::assertFileDoesNotExist(
            dirname(__DIR__, 3) . '/public/js/subscription-account-pause.js',
        );
    }

    public function test_shared_view_renders_backend_driven_no_active_subscription_promo(): void
    {
        $source = $this->read('views/subscriptions/shared/_subscription_account.php');

        self::assertStringContainsString('$hasLiveSubscriptions', $source);
        self::assertStringContainsString('subscription-acquisition-promo', $source);
        self::assertStringContainsString('See Subscription Options', $source);
        self::assertStringContainsString('data-open-subscription-modal', $source);
        self::assertStringContainsString('previous-subscriptions" <?= !$hasLiveSubscriptions ? \'open\' : \'\' ?>', $source);
    }

    public function test_shared_view_loads_payment_recovery_modal_controller(): void
    {
        $source = $this->read('views/subscriptions/shared/_subscription_account.php');

        self::assertStringContainsString('payment-recovery-modal', $source);
        self::assertStringContainsString('payment-recovery-frame', $source);
        self::assertStringContainsString('subscription-account-payment-recovery.js', $source);
    }

    public function test_subscription_card_uses_backend_actions_for_payment_recovery(): void
    {
        $source = $this->read('views/subscriptions/account/_subscription_card.php');

        self::assertStringContainsString('$actionKey === \'settle_payment\'', $source);
        self::assertStringContainsString('data-open-payment-recovery', $source);
        self::assertStringContainsString('data-payment-recovery-url', $source);
        self::assertStringContainsString('Settle Payment', $source);
    }

    private function read(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertNotFalse($source);

        return $source;
    }
}
