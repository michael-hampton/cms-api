<?php

namespace App\Tests\Unit\Services\OpenCollab\Notifications;

use App\Framework\Notifications\AdminNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Models\ArticlePayment;
use App\Models\EarningsDispute;
use App\Models\Invitation;
use App\Models\Page;
use App\Models\Payout;
use App\Models\User;
use App\Services\OpenCollab\Notifications\ArticlePaymentFailedNotification;
use App\Services\OpenCollab\Notifications\ArticlePaymentSucceededNotification;
use App\Services\OpenCollab\Notifications\DisputeAdjustmentAppliedNotification;
use App\Services\OpenCollab\Notifications\DisputeRaisedNotification;
use App\Services\OpenCollab\Notifications\DisputeResolvedNotification;
use App\Services\OpenCollab\Notifications\InvitationAcceptedNotification;
use App\Services\OpenCollab\Notifications\InvitationCreatedNotification;
use App\Services\OpenCollab\Notifications\InvitationResentNotification;
use App\Services\OpenCollab\Notifications\OnboardingCompletedNotification;
use App\Services\OpenCollab\Notifications\OnboardingStartedNotification;
use App\Services\OpenCollab\Notifications\OnboardingStepCompletedNotification;
use App\Services\OpenCollab\Notifications\PaymentRetryAvailableNotification;
use App\Services\OpenCollab\Notifications\PayoutApprovedNotification;
use App\Services\OpenCollab\Notifications\PayoutCreatedNotification;
use App\Services\OpenCollab\Notifications\PayoutDeclinedNotification;
use App\Services\OpenCollab\Notifications\PayoutPaidNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainNotificationsTest extends TestCase
{
    // ── Invitation ────────────────────────────────────────────────────────────

    public static function emailableNotificationProvider(): array
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'Test';
        $user->email = 'c@example.com';
        $invitation = new Invitation();
        $invitation->email = 'c@example.com';
        $payout = new Payout();
        $payout->id = 1;
        $payout->amount = 1000;
        $payment = new ArticlePayment();
        $payment->email = 'c@example.com';
        $payment->user_id = 1;
        $page = new Page();
        $page->title = 'Article';
        $dispute = new EarningsDispute();

        return [
            'InvitationCreated' => [new InvitationCreatedNotification($invitation)],
            'InvitationResent' => [new InvitationResentNotification($invitation)],
            'InvitationAccepted' => [new InvitationAcceptedNotification($user, $invitation)],
            'PayoutCreated' => [new PayoutCreatedNotification($payout, $user)],
            'PayoutApproved' => [new PayoutApprovedNotification($payout, $user)],
            'PayoutDeclined' => [new PayoutDeclinedNotification($payout, $user)],
            'PayoutPaid' => [new PayoutPaidNotification($payout, $user)],
            'ArticlePaymentSucceeded' => [new ArticlePaymentSucceededNotification($payment, $page)],
            'ArticlePaymentFailed' => [new ArticlePaymentFailedNotification($payment, $page)],
            'PaymentRetryAvailable' => [new PaymentRetryAvailableNotification($payment, $page)],
            'DisputeRaised' => [new DisputeRaisedNotification($dispute, $user)],
            'DisputeResolved(approved)' => [new DisputeResolvedNotification($dispute, $user, true)],
            'DisputeResolved(rejected)' => [new DisputeResolvedNotification($dispute, $user, false)],
            'DisputeAdjustmentApplied' => [new DisputeAdjustmentAppliedNotification($dispute, $user, 500, 'GBP')],
            'OnboardingStarted' => [new OnboardingStartedNotification($user, 1)],
            'OnboardingCompleted' => [new OnboardingCompletedNotification($user, 1)],
        ];
    }

    public function testInvitationCreatedIsEmailable(): void
    {
        $n = new InvitationCreatedNotification($this->makeInvitation('bob@example.com'));

        $this->assertInstanceOf(EmailableNotification::class, $n);
        $this->assertSame('bob@example.com', $n->recipientEmail());
        $this->assertNull($n->recipientUserId());
        $this->assertNotEmpty($n->subject());
        $this->assertNotNull($n->toMailable());
    }

    private function makeInvitation(string $email): Invitation
    {
        $i = new Invitation();
        $i->email = $email;
        return $i;
    }

    // ── Payout ────────────────────────────────────────────────────────────────

    public function testInvitationResentHasDifferentSubjectToCreated(): void
    {
        $invitation = $this->makeInvitation('bob@example.com');

        $created = new InvitationCreatedNotification($invitation);
        $resent = new InvitationResentNotification($invitation);

        $this->assertNotSame($created->subject(), $resent->subject());
        $this->assertSame('bob@example.com', $resent->recipientEmail());
    }

    public function testInvitationAcceptedTargetsContributorNotInvitationEmail(): void
    {
        $contributor = $this->makeUser(1, 'alice@example.com');
        $invitation = $this->makeInvitation('old@example.com');

        $n = new InvitationAcceptedNotification($contributor, $invitation);

        $this->assertSame(1, $n->recipientUserId());
        $this->assertSame('alice@example.com', $n->recipientEmail());
        $this->assertNotNull($n->toMailable());
    }

    private function makeUser(int $id, string $email): User
    {
        $u = new User();
        $u->id = $id;
        $u->name = "User {$id}";
        $u->email = $email;
        return $u;
    }

    public function testPayoutCreatedSubjectContainsFormattedAmount(): void
    {
        $n = new PayoutCreatedNotification(
            $this->makePayout(1050),
            $this->makeUser(2, 'c@example.com')
        );

        $this->assertStringContainsString('10.50', $n->subject());
        $this->assertSame(2, $n->recipientUserId());
        $this->assertNotNull($n->toMailable());
    }

    // ── Payment ───────────────────────────────────────────────────────────────

    private function makePayout(int $amountPence): Payout
    {
        $p = new Payout();
        $p->id = 1;
        $p->amount = $amountPence;
        return $p;
    }

    public function testPayoutApprovedSubjectContainsApproved(): void
    {
        $n = new PayoutApprovedNotification(
            $this->makePayout(2000),
            $this->makeUser(3, 'c@example.com')
        );

        $this->assertStringContainsString('approved', strtolower($n->subject()));
        $this->assertNotNull($n->toMailable());
    }

    public function testPayoutDeclinedCarriesReason(): void
    {
        $n = new PayoutDeclinedNotification(
            $this->makePayout(500),
            $this->makeUser(4, 'c@example.com'),
            'Missing bank details'
        );

        $this->assertSame('Missing bank details', $n->reason);
        $this->assertNotNull($n->toMailable());
    }

    // ── Dispute ───────────────────────────────────────────────────────────────

    public function testPayoutPaidCarriesReference(): void
    {
        $n = new PayoutPaidNotification(
            $this->makePayout(5000),
            $this->makeUser(5, 'c@example.com'),
            'REF-001'
        );

        $this->assertSame('REF-001', $n->reference);
        $this->assertStringContainsString('sent', strtolower($n->subject()));
        $this->assertNotNull($n->toMailable());
    }

    public function testArticlePaymentSucceededUsesPaymentEmail(): void
    {
        $payment = $this->makePayment('buyer@example.com');
        $page = $this->makePage('My Article');

        $n = new ArticlePaymentSucceededNotification($payment, $page);

        $this->assertSame('buyer@example.com', $n->recipientEmail());
        $this->assertStringContainsString('My Article', $n->subject());
        $this->assertNotNull($n->toMailable());
    }

    private function makePayment(string $email): ArticlePayment
    {
        $p = new ArticlePayment();
        $p->email = $email;
        $p->user_id = null;
        return $p;
    }

    private function makePage(string $title): Page
    {
        $p = new Page();
        $p->title = $title;
        return $p;
    }

    public function testArticlePaymentFailedSubjectMentionsFailure(): void
    {
        $n = new ArticlePaymentFailedNotification(
            $this->makePayment('buyer@example.com'),
            $this->makePage('My Article'),
        );

        $this->assertStringContainsString('failed', strtolower($n->subject()));
        $this->assertNotNull($n->toMailable());
    }

    // ── Onboarding ────────────────────────────────────────────────────────────

    public function testPaymentRetryAvailableSubjectMentionsRetry(): void
    {
        $n = new PaymentRetryAvailableNotification(
            $this->makePayment('buyer@example.com'),
            $this->makePage('My Article'),
        );

        $this->assertStringContainsString('retry', strtolower($n->subject()));
        $this->assertNotNull($n->toMailable());
    }

    public function testDisputeRaisedImplementsAdminNotification(): void
    {
        $n = new DisputeRaisedNotification(
            new EarningsDispute(),
            $this->makeUser(6, 'c@example.com')
        );

        $this->assertInstanceOf(AdminNotification::class, $n);
        $this->assertInstanceOf(EmailableNotification::class, $n);
        // Admin notifications intentionally have no recipient on the notification itself
        $this->assertNull($n->recipientUserId());
        $this->assertNull($n->recipientEmail());
        $this->assertNotNull($n->toMailable());
    }

    public function testDisputeRaisedSubjectContainsContributorName(): void
    {
        $contributor = $this->makeUser(7, 'c@example.com');
        $n = new DisputeRaisedNotification(new EarningsDispute(), $contributor);

        $this->assertStringContainsString($contributor->name, $n->subject());
    }

    // ── toMailable returns the correct type ───────────────────────────────────

    public function testDisputeResolvedApprovedSubjectIndicatesFavour(): void
    {
        $n = new DisputeResolvedNotification(
            new EarningsDispute(),
            $this->makeUser(8, 'c@example.com'),
            wasApproved: true,
        );

        $this->assertStringContainsString('favour', strtolower($n->subject()));
        $this->assertNotNull($n->toMailable());
    }

    public function testDisputeResolvedRejectedSubjectIsNeutral(): void
    {
        $n = new DisputeResolvedNotification(
            new EarningsDispute(),
            $this->makeUser(9, 'c@example.com'),
            wasApproved: false,
        );

        $this->assertStringNotContainsString('favour', strtolower($n->subject()));
    }

    // ── Factories ─────────────────────────────────────────────────────────────

    public function testDisputeAdjustmentSubjectContainsSignedAmount(): void
    {
        $nCredit = new DisputeAdjustmentAppliedNotification(
            new EarningsDispute(),
            $this->makeUser(10, 'c@example.com'),
            adjustmentAmountPence: 500,
            currency: 'GBP',
        );
        $nDebit = new DisputeAdjustmentAppliedNotification(
            new EarningsDispute(),
            $this->makeUser(10, 'c@example.com'),
            adjustmentAmountPence: -500,
            currency: 'GBP',
        );

        $this->assertStringContainsString('+', $nCredit->subject());
        $this->assertStringContainsString('−', $nDebit->subject());
        $this->assertNotNull($nCredit->toMailable());
    }

    public function testOnboardingStartedIsEmailable(): void
    {
        $n = new OnboardingStartedNotification(
            $this->makeUser(11, 'c@example.com'),
            siteId: 1
        );

        $this->assertInstanceOf(EmailableNotification::class, $n);
        $this->assertSame(11, $n->recipientUserId());
        $this->assertNotNull($n->toMailable());
    }

    public function testOnboardingStepCompletedIsNotEmailable(): void
    {
        // Step completion is intentionally suppressed from email.
        $n = new OnboardingStepCompletedNotification(
            $this->makeUser(12, 'c@example.com'),
            step: 'profile',
            remainingSteps: ['payment', 'contract'],
        );

        $this->assertNotInstanceOf(EmailableNotification::class, $n);
        $this->assertStringContainsString('Profile', $n->subject());
    }

    public function testOnboardingCompletedIsEmailable(): void
    {
        $n = new OnboardingCompletedNotification(
            $this->makeUser(13, 'c@example.com'),
            siteId: 1
        );

        $this->assertInstanceOf(EmailableNotification::class, $n);
        $this->assertStringContainsString('publishing', strtolower($n->subject()));
        $this->assertNotNull($n->toMailable());
    }

    /**
     * Each EmailableNotification must return a Mailable from toMailable().
     * This is a lightweight smoke-test — it does not assert which mailable class
     * is returned (that is the domain's concern).
     *
     */
    #[DataProvider('emailableNotificationProvider')]
    public function testToMailableReturnsMailableInstance(EmailableNotification $n): void
    {
        $this->assertInstanceOf(\App\Framework\Mail\Mailable::class, $n->toMailable());
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}