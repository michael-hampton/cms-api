<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Mail\Alerts\BundleExpiringSoonMerchantMail;
use App\Models\Merchant;
use App\Models\ProductOfferBundle;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class BundleExpiringSoonMerchantMailTest extends FunctionalTestCase
{
    public function testSubjectContainsHoursRemainingAndBundleName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertStringContainsString('24 hours', $mailable->subject);
        $this->assertStringContainsString('Summer Bundle', $mailable->subject);
    }

    public function testSubjectReflects48HourThreshold(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertStringContainsString('48 hours', $mailable->subject);
    }

    public function testRecipientIsNotSetByBuild(): void
    {
        // Recipient is set by the service via $mailer->to($contact->email)->send().
        // build() must not set ->to() — merchants have no email field.
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertEmpty($mailable->to);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('emails.alerts.bundle-expiring-soon-merchant', $mailable->markdown);
    }

    public function testViewDataContainsHoursRemaining(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertSame(48, $mailable->viewData['hoursRemaining']);
    }

    public function testViewDataContainsBundleName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('Summer Bundle', $mailable->viewData['bundleName']);
    }

    public function testViewDataContainsBundlePrice(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame(49.99, $mailable->viewData['bundlePrice']);
    }

    public function testViewDataContainsTotalValue(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame(79.99, $mailable->viewData['totalValue']);
    }

    public function testViewDataContainsDiscountPercentage(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame(37, $mailable->viewData['discountPercentage']);
    }

    public function testViewDataContainsExpiresAt(): void
    {
        $expiresAt = new \DateTime('+20 hours');
        $bundle = $this->makeBundle(endDate: $expiresAt);
        $mailable = $this->makeMail(bundle: $bundle);
        $mailable->build();

        $this->assertSame($expiresAt->format('Y-m-d'), $mailable->viewData['expiresAt']->format('Y-m-d'));
    }

    public function testViewDataContainsMerchant(): void
    {
        $merchant = $this->makeMerchant();
        $mailable = $this->makeMail(merchant: $merchant);
        $mailable->build();

        $this->assertSame($merchant, $mailable->viewData['merchant']);
    }

    public function testViewDataContainsBundle(): void
    {
        $bundle = $this->makeBundle();
        $mailable = $this->makeMail(bundle: $bundle);
        $mailable->build();

        $this->assertSame($bundle, $mailable->viewData['bundle']);
    }

    public function testManageUrlContainsBundleId(): void
    {
        $bundle = $this->makeBundle(id: 55);
        $mailable = $this->makeMail(bundle: $bundle);
        $mailable->build();

        $this->assertStringContainsString('/merchant/bundles/55', $mailable->viewData['manageUrl']);
    }

    public function testRendersWithMerchantName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Acme Corp', $html);
    }

    public function testRendersWithBundleName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Summer Bundle', $html);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeMail(
        ?Merchant            $merchant = null,
        ?ProductOfferBundle  $bundle = null,
        ExpiryAlertThreshold $threshold = ExpiryAlertThreshold::TwentyFourHours,
    ): BundleExpiringSoonMerchantMail
    {
        return new BundleExpiringSoonMerchantMail(
            merchant: $merchant ?? $this->makeMerchant(),
            bundle: $bundle ?? $this->makeBundle(),
            threshold: $threshold,
        );
    }

    private function makeMerchant(): Merchant
    {
        $merchant = new Merchant();
        $merchant->name = 'Acme Corp';
        return $merchant;
    }

    private function makeBundle(int $id = 20, ?\DateTimeInterface $endDate = null): ProductOfferBundle
    {
        $bundle = $this->createPartialMock(ProductOfferBundle::class, ['items']);
        $bundle->id = $id;
        $bundle->name = 'Summer Bundle';
        $bundle->slug = 'summer-bundle';
        $bundle->bundle_price = 49.99;
        $bundle->total_price = 79.99;
        $bundle->discount_percentage = 37;
        $bundle->end_date = $endDate ?? new \DateTime('+20 hours');

        // items(true) is called inside build() to get count
        $bundle->method('items')->willReturn(collect([new \stdClass(), new \stdClass(), new \stdClass()]));

        return $bundle;
    }
}