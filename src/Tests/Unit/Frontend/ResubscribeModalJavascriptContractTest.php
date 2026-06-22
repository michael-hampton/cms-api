<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class ResubscribeModalJavascriptContractTest extends TestCase
{
    public function test_subscription_card_renders_resubscribe_modal_trigger(): void
    {
        $source = $this->read('views/subscriptions/account/_subscription_card.php');

        self::assertStringContainsString("'subscription_checkout'", $source);
        self::assertStringContainsString('data-open-subscription-modal', $source);
        self::assertStringContainsString('data-plan-id', $source);
        self::assertStringContainsString('data-plan-slug', $source);
        self::assertStringContainsString('data-source-subscription-id', $source);
    }

    public function test_acquisition_script_selects_and_visibly_marks_plan(): void
    {
        $source = $this->read('public/js/subscription-account-acquisition.js');

        self::assertStringContainsString('function findPlanElement(planSlug, planId)', $source);
        self::assertStringContainsString('markPlanSelected(planElement)', $source);
        self::assertStringContainsString('sub-plan--current', $source);
        self::assertStringContainsString('Current plan', $source);
        self::assertStringContainsString('Selected plan', $source);
        self::assertStringContainsString('manager.readPlanData(planElement)', $source);
        self::assertStringContainsString('manager.goToStep(manager.nextStep(1))', $source);
        self::assertStringContainsString('resubscribe_from_subscription_id', $source);
    }

    public function test_acquisition_script_requires_delivery_type_before_cart_add(): void
    {
        $source = $this->read('public/js/subscription-account-acquisition.js');

        self::assertStringContainsString('manager.api.addPlanToCart', $source);
        self::assertStringContainsString('!plan.deliveryType', $source);
        self::assertStringContainsString('A delivery type is required', $source);
        self::assertStringContainsString('missing a delivery type', $source);
    }

    public function test_press_stack_account_loads_acquisition_script(): void
    {
        $source = $this->read('views/subscriptions/account/subscriptions.php');

        self::assertStringContainsString('subscription-account-acquisition.js', $source);
    }

    private function read(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertNotFalse($source);

        return $source;
    }
}
