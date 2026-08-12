<?php

namespace App\Tests\Unit\Services\Subscriptions\DeliveryChannels;

use App\DTO\Newsletters\NewsletterAccessResult;
use App\Framework\Notifications\MailableNotification;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\IssueDelivery;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Subscriptions\DeliveryChannels\EmailDeliveryChannel;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class EmailDeliveryChannelTest extends UnitTestCase
{
    private NewsletterContentBuilder|MockInterface $contentBuilder;
    private NotificationDispatcher|MockInterface $notificationDispatcher;
    private NewsletterRepository|MockInterface $newsletterRepository;
    private EmailDeliveryChannel $channel;

    public function test_sends_email_when_all_conditions_are_met(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();
        $newsletter = $this->makeNewsletter();
        $subscription = $this->makeSubscription($member, $plan);
        $issue = $this->makeIssueDelivery($plan);

        [$sub, $iss] = $this->stubOrmRelations($subscription, $member, $issue, $plan);

        $sub->shouldReceive('canAccessNewsletter')
            ->with($newsletter, $member)
            ->once()
            ->andReturn(NewsletterAccessResult::allowed());

        $this->newsletterRepository
            ->shouldReceive('findBySlugAndSite')
            ->with('insider', 1)
            ->once()
            ->andReturn($newsletter);

        $this->contentBuilder
            ->shouldReceive('build')
            ->with($newsletter, 1, false, $member)
            ->once()
            ->andReturn(['success' => true, 'html' => '<html>newsletter</html>', 'pages' => []]);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(MailableNotification::class))
            ->andReturn(1);

        $this->channel->send($sub, $iss);
        $this->assertTrue(true);
    }

    private function makeMember(array $overrides = []): Member
    {
        $member = new Member(array_merge([
            'id' => 1,
            'email' => 'reader@example.com',
            'first_name' => 'Test',
            'last_name' => 'Reader',
        ], $overrides));
        $member->setExists(true);
        return $member;
    }

    private function makePlan(array $overrides = []): SubscriptionPlan
    {
        $plan = new SubscriptionPlan(array_merge([
            'id' => 10,
            'site_id' => 1,
            'name' => 'Full Access',
            'premium_access' => json_encode([
                ['type' => 'newsletter', 'identifier' => 'insider'],
            ]),
        ], $overrides));
        $plan->setExists(true);
        return $plan;
    }

    private function makeNewsletter(array $overrides = []): Newsletter
    {
        $newsletter = new Newsletter(array_merge([
            'id' => 5,
            'site_id' => 1,
            'slug' => 'insider',
            'title' => 'The Insider',
        ], $overrides));
        $newsletter->setExists(true);
        return $newsletter;
    }

    private function makeSubscription(Member $member, SubscriptionPlan $plan, array $overrides = []): Subscription
    {
        $subscription = new Subscription(array_merge([
            'id' => 99,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => 1,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
            'end_date' => (new \DateTime('+1 year'))->format('Y-m-d H:i:s'),
        ], $overrides));
        $subscription->setExists(true);
        return $subscription;
    }

    private function makeIssueDelivery(SubscriptionPlan $plan, array $overrides = []): IssueDelivery
    {
        $issue = new IssueDelivery(array_merge([
            'id' => 7,
            'subscription_plan_id' => $plan->id,
            'issue_title' => 'Issue #42',
            'site_id' => 1,
            'status' => 'active',
        ], $overrides));
        $issue->setExists(true);
        return $issue;
    }

    private function stubOrmRelations(
        Subscription     $subscription,
        Member           $member,
        IssueDelivery    $issue,
        SubscriptionPlan $plan
    ): array {
        $memberQuery = Mockery::mock();
        $memberQuery->shouldReceive('first')->andReturn($member);

        $subscriptionPartial = Mockery::mock($subscription)->makePartial();
        $subscriptionPartial->shouldReceive('member')->with(true)->andReturn($memberQuery);

        $planQuery = Mockery::mock();
        $planQuery->shouldReceive('first')->andReturn($plan);

        $issuePartial = Mockery::mock($issue)->makePartial();
        $issuePartial->shouldReceive('subscriptionPlans')->andReturn($planQuery);

        return [$subscriptionPartial, $issuePartial];
    }

    public function test_throws_when_member_not_found(): void
    {
        $plan = $this->makePlan();
        $issue = $this->makeIssueDelivery($plan);

        $memberQuery = Mockery::mock();
        $memberQuery->shouldReceive('first')->once()->andReturn(null);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('member')->with(true)->andReturn($memberQuery);
        $subscription->id = 99;

        $this->contentBuilder->shouldNotReceive('build');
        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/member or email not found/');

        $this->channel->send($subscription, $issue);
    }

    public function test_throws_when_member_has_no_email(): void
    {
        $member = $this->makeMember(['email' => null]);
        $plan = $this->makePlan();
        $issue = $this->makeIssueDelivery($plan);

        $memberQuery = Mockery::mock();
        $memberQuery->shouldReceive('first')->once()->andReturn($member);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('member')->with(true)->andReturn($memberQuery);
        $subscription->id = 99;

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/member or email not found/');

        $this->channel->send($subscription, $issue);
    }

    public function test_throws_when_plan_not_found(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();
        $issue = $this->makeIssueDelivery($plan);

        $memberQuery = Mockery::mock();
        $memberQuery->shouldReceive('first')->once()->andReturn($member);

        $planQuery = Mockery::mock();
        $planQuery->shouldReceive('first')->once()->andReturn(null);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('member')->with(true)->andReturn($memberQuery);

        $issueMock = Mockery::mock($issue)->makePartial();
        $issueMock->shouldReceive('subscriptionPlans')->andReturn($planQuery);
        $issueMock->id = 7;

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no subscription plan found/');

        $this->channel->send($subscription, $issueMock);
    }

    public function test_throws_when_plan_has_no_newsletter_grant(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan(['premium_access' => json_encode([
            ['type' => 'feature', 'identifier' => 'downloads'],
        ])]);
        $issue = $this->makeIssueDelivery($plan);

        $memberQuery = Mockery::mock();
        $memberQuery->shouldReceive('first')->andReturn($member);

        $planQuery = Mockery::mock();
        $planQuery->shouldReceive('first')->andReturn($plan);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('member')->with(true)->andReturn($memberQuery);

        $issueMock = Mockery::mock($issue)->makePartial();
        $issueMock->shouldReceive('subscriptionPlans')->andReturn($planQuery);
        $issueMock->id = 7;

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no newsletter premium access grant/');

        $this->channel->send($subscription, $issueMock);
    }

    public function test_throws_when_subscription_access_denied(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();
        $newsletter = $this->makeNewsletter();
        $subscription = $this->makeSubscription($member, $plan, ['status' => 'cancelled']);
        $issue = $this->makeIssueDelivery($plan);

        [$sub, $iss] = $this->stubOrmRelations($subscription, $member, $issue, $plan);

        $this->newsletterRepository
            ->shouldReceive('findBySlugAndSite')
            ->andReturn($newsletter);

        $sub->shouldReceive('canAccessNewsletter')
            ->andReturn(NewsletterAccessResult::denied('subscription_not_eligible', 'cancelled'));

        $this->contentBuilder->shouldNotReceive('build');
        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/access denied/');

        $this->channel->send($sub, $iss);
    }

    public function test_throws_when_content_build_fails(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();
        $newsletter = $this->makeNewsletter();
        $subscription = $this->makeSubscription($member, $plan);
        $issue = $this->makeIssueDelivery($plan);

        [$sub, $iss] = $this->stubOrmRelations($subscription, $member, $issue, $plan);

        $this->newsletterRepository
            ->shouldReceive('findBySlugAndSite')
            ->andReturn($newsletter);

        $sub->shouldReceive('canAccessNewsletter')
            ->andReturn(NewsletterAccessResult::allowed());

        $this->contentBuilder
            ->shouldReceive('build')
            ->once()
            ->andReturn(['success' => false, 'error' => 'No pages found']);

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/content build failed/');

        $this->channel->send($sub, $iss);
    }

    public function test_unsubscribe_placeholder_replaced_in_html(): void
    {
        $member = $this->makeMember(['email' => 'reader@example.com']);
        $plan = $this->makePlan();
        $newsletter = $this->makeNewsletter();
        $subscription = $this->makeSubscription($member, $plan);
        $issue = $this->makeIssueDelivery($plan);

        [$sub, $iss] = $this->stubOrmRelations($subscription, $member, $issue, $plan);

        $this->newsletterRepository->shouldReceive('findBySlugAndSite')->andReturn($newsletter);
        $sub->shouldReceive('canAccessNewsletter')->andReturn(NewsletterAccessResult::allowed());

        $this->contentBuilder
            ->shouldReceive('build')
            ->andReturn([
                'success' => true,
                'html' => '<p>Click {{UNSUBSCRIBE_LINK}} to unsubscribe</p>',
                'pages' => [],
            ]);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function (MailableNotification $notification) {
                $html = $notification->toMailable()->render();

                $this->assertStringNotContainsString('{{UNSUBSCRIBE_LINK}}', $html);
                $this->assertStringContainsString(urlencode('reader@example.com'), $html);
                return true;
            }))
            ->andReturn(1);

        $this->channel->send($sub, $iss);
    }

    public function test_issue_title_used_as_email_subject(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();
        $newsletter = $this->makeNewsletter();
        $subscription = $this->makeSubscription($member, $plan);
        $issue = $this->makeIssueDelivery($plan, ['issue_title' => 'March Edition']);

        [$sub, $iss] = $this->stubOrmRelations($subscription, $member, $issue, $plan);

        $this->newsletterRepository->shouldReceive('findBySlugAndSite')->andReturn($newsletter);
        $sub->shouldReceive('canAccessNewsletter')->andReturn(NewsletterAccessResult::allowed());
        $this->contentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => '<p>content</p>', 'pages' => []]);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function (MailableNotification $notification) {
                $this->assertSame('March Edition', $notification->subject());
                return true;
            }))
            ->andReturn(1);

        $this->channel->send($sub, $iss);
    }

    public function test_falls_back_to_newsletter_title_when_issue_has_no_title(): void
    {
        $member = $this->makeMember();
        $plan = $this->makePlan();
        $newsletter = $this->makeNewsletter(['title' => 'The Insider']);
        $subscription = $this->makeSubscription($member, $plan);
        $issue = $this->makeIssueDelivery($plan, ['issue_title' => null]);

        [$sub, $iss] = $this->stubOrmRelations($subscription, $member, $issue, $plan);

        $this->newsletterRepository->shouldReceive('findBySlugAndSite')->andReturn($newsletter);
        $sub->shouldReceive('canAccessNewsletter')->andReturn(NewsletterAccessResult::allowed());
        $this->contentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => '<p>content</p>', 'pages' => []]);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function (MailableNotification $notification) {
                $this->assertSame('The Insider', $notification->subject());
                return true;
            }))
            ->andReturn(1);

        $this->channel->send($sub, $iss);
    }

    protected function setUp(): void
    {

        $this->contentBuilder = Mockery::mock(NewsletterContentBuilder::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);

        $this->channel = new EmailDeliveryChannel(
            $this->contentBuilder,
            $this->notificationDispatcher,
            $this->newsletterRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
