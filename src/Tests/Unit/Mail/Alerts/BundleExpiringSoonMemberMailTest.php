<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Mail\Alerts\BundleExpiringSoonMemberMail;
use App\Models\Member;
use App\Models\ProductOfferBundle;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class BundleExpiringSoonMemberMailTest extends FunctionalTestCase
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

    public function testRecipientIsSetToMemberEmail(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertNotEmpty($mailable->to);
        $this->assertSame('member@example.com', $mailable->to[0]['address']);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();

        $this->assertSame('emails.alerts.bundle-expiring-soon-member', $mailable->markdown);
    }

    public function testViewDataContainsHoursRemaining(): void
    {
        $mailable = $this->makeMail(threshold: ExpiryAlertThreshold::FortyEightHours);
        $mailable->build();

        $this->assertSame(48, $mailable->viewData['hoursRemaining']);
    }

    public function testViewDataContainsMember(): void
    {
        $member = $this->makeMember();
        $mailable = $this->makeMail(member: $member);
        $mailable->build();

        $this->assertSame($member, $mailable->viewData['member']);
    }

    public function testViewDataContainsBundle(): void
    {
        $bundle = $this->makeBundle();
        $mailable = $this->makeMail(bundle: $bundle);
        $mailable->build();

        $this->assertSame($bundle, $mailable->viewData['bundle']);
    }

    public function testViewDataContainsExpiresAt(): void
    {
        $expiresAt = new \DateTime('+20 hours');
        $bundle = $this->makeBundle(endDate: $expiresAt);
        $mailable = $this->makeMail(bundle: $bundle);
        $mailable->build();

        $this->assertSame($expiresAt->format('Y-m-d'), $mailable->viewData['expiresAt']->format('Y-m-d'));
    }

    public function testBundleUrlContainsSlug(): void
    {
        $bundle = $this->makeBundle();
        $mailable = $this->makeMail(bundle: $bundle);
        $mailable->build();

        $this->assertStringContainsString('summer-bundle', $mailable->viewData['bundleUrl']);
    }

    public function testRendersWithBundleName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Summer Bundle', $html);
    }

    public function testRendersWithMemberFirstName(): void
    {
        $mailable = $this->makeMail();
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Alice', $html);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeMail(
        ?Member              $member = null,
        ?ProductOfferBundle  $bundle = null,
        ExpiryAlertThreshold $threshold = ExpiryAlertThreshold::TwentyFourHours,
    ): BundleExpiringSoonMemberMail
    {
        return new BundleExpiringSoonMemberMail(
            member: $member ?? $this->makeMember(),
            bundle: $bundle ?? $this->makeBundle(),
            threshold: $threshold,
        );
    }

    private function makeMember(): Member
    {
        $member = new Member();
        $member->email = 'member@example.com';
        $member->first_name = 'Alice';
        return $member;
    }

    private function makeBundle(?\DateTimeInterface $endDate = null): ProductOfferBundle
    {
        $bundle = new ProductOfferBundle();
        $bundle->name = 'Summer Bundle';
        $bundle->slug = 'summer-bundle';
        $bundle->description = 'Great deals bundled together.';
        $bundle->bundle_price = 49.99;
        $bundle->total_price = 79.99;
        $bundle->discount_percentage = 37;
        $bundle->end_date = $endDate ?? new \DateTime('+20 hours');
        return $bundle;
    }
}