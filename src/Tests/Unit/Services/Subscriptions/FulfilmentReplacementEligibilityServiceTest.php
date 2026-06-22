<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\FulfilmentReplacement;
use App\Models\Subscription;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use Mockery;
use PHPUnit\Framework\TestCase;

class FulfilmentReplacementEligibilityServiceTest extends TestCase
{
    private FulfilmentReplacementRepository $replacementRepository;
    private SubscriptionRepository $subscriptionRepository;
    private FulfilmentReplacementEligibilityService $service;

    public function test_denied_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn(null);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('not found', $result->blockedReason);
    }

    public function test_denied_when_subscription_belongs_to_wrong_site(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(siteId: 99));

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('site', $result->blockedReason);
    }

    public function test_denied_when_subscription_is_not_active(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(status: 'cancelled'));

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('active', $result->blockedReason);
    }

    public function test_denied_when_subscription_is_paused(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(status: 'paused'));

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('active', $result->blockedReason);
    }

    public function test_denied_when_subscription_is_digital(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(deliveryType: 'digital'));

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('print', $result->blockedReason);
    }

    public function test_denied_when_subscription_has_no_plan(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(planId: null));

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('subscription plan', $result->blockedReason);
    }

    public function test_denied_when_issue_does_not_belong_to_subscription_plan(): void
    {
        $this->stubActiveSubscription();
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->once()->with(100, 123)->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('does not belong', $result->blockedReason);
    }

    public function test_denied_when_issue_has_not_been_dispatched(): void
    {
        $this->stubActiveSubscription();
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->once()->with(100, 123)->andReturn(true);
        $this->replacementRepository->shouldReceive('issueDeliveryWasDispatchedForSubscriptionPlan')->once()->with(100, 123)->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('dispatched', $result->blockedReason);
    }

    public function test_denied_when_open_pending_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();
        $this->replacementRepository->shouldReceive('hasOpenReplacement')->once()->with(1, 100)->andReturn(true);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('already in progress', $result->blockedReason);
    }

    public function test_denied_when_open_queued_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();
        $this->replacementRepository->shouldReceive('hasOpenReplacement')->once()->andReturn(true);

        $this->assertFalse($this->service->canRequest(1, 100, 1)->canRequestReplacement);
    }

    public function test_denied_when_open_dispatched_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();
        $this->replacementRepository->shouldReceive('hasOpenReplacement')->once()->andReturn(true);

        $this->assertFalse($this->service->canRequest(1, 100, 1)->canRequestReplacement);
    }

    public function test_allowed_when_failed_replacement_exists(): void
    {
        $this->assertAllowedWhenNoOpenReplacement();
    }

    public function test_allowed_when_rejected_replacement_exists(): void
    {
        $this->assertAllowedWhenNoOpenReplacement();
    }

    public function test_allowed_when_cancelled_replacement_exists(): void
    {
        $this->assertAllowedWhenNoOpenReplacement();
    }

    public function test_allowed_for_valid_print_dispatched_issue_with_no_open_replacement(): void
    {
        $this->assertAllowedWhenNoOpenReplacement();
    }

    public function test_bulk_returns_empty_array_for_empty_issue_list(): void
    {
        $this->assertSame([], $this->service->canRequestForIssues(1, [], 1));
    }

    public function test_bulk_denies_all_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn(null);

        $results = $this->service->canRequestForIssues(1, [10, 20], 1);

        $this->assertFalse($results[10]->canRequestReplacement);
        $this->assertFalse($results[20]->canRequestReplacement);
        $this->assertStringContainsString('not found', $results[10]->blockedReason);
    }

    public function test_bulk_denies_all_for_digital_subscription(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(deliveryType: 'digital'));

        $results = $this->service->canRequestForIssues(1, [10, 20, 30], 1);

        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertFalse($result->canRequestReplacement);
        }
    }

    public function test_bulk_denies_all_when_subscription_has_no_plan(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(planId: null));

        $results = $this->service->canRequestForIssues(1, [10, 20], 1);

        $this->assertFalse($results[10]->canRequestReplacement);
        $this->assertFalse($results[20]->canRequestReplacement);
        $this->assertStringContainsString('subscription plan', $results[10]->blockedReason);
    }

    public function test_bulk_returns_mixed_results_for_mixed_issue_list(): void
    {
        $this->stubActiveSubscription();
        $this->replacementRepository->shouldReceive('findOpenReplacementsForIssues')
            ->once()->with(1, [10, 20, 30])->andReturn(collect([$this->makeReplacement(30)]));
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->with(10, 123)->andReturn(true);
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->with(20, 123)->andReturn(true);
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->with(30, 123)->andReturn(true);
        $this->replacementRepository->shouldReceive('issueDeliveryWasDispatchedForSubscriptionPlan')->with(10, 123)->andReturn(true);
        $this->replacementRepository->shouldReceive('issueDeliveryWasDispatchedForSubscriptionPlan')->with(20, 123)->andReturn(false);
        $this->replacementRepository->shouldReceive('issueDeliveryWasDispatchedForSubscriptionPlan')->with(30, 123)->andReturn(true);

        $results = $this->service->canRequestForIssues(1, [10, 20, 30], 1);

        $this->assertTrue($results[10]->canRequestReplacement);
        $this->assertFalse($results[20]->canRequestReplacement);
        $this->assertStringContainsString('dispatched', $results[20]->blockedReason);
        $this->assertFalse($results[30]->canRequestReplacement);
        $this->assertStringContainsString('already in progress', $results[30]->blockedReason);
    }

    public function test_bulk_only_fetches_open_replacements_once(): void
    {
        $this->stubActiveSubscription();
        $this->replacementRepository->shouldReceive('findOpenReplacementsForIssues')->once()->andReturn(collect([]));
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->andReturn(true);
        $this->replacementRepository->shouldReceive('issueDeliveryWasDispatchedForSubscriptionPlan')->with(Mockery::any(), 123)->andReturn(true);

        $this->service->canRequestForIssues(1, [10, 20, 30], 1);
        $this->assertTrue(true);
    }

    private function assertAllowedWhenNoOpenReplacement(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();
        $this->replacementRepository->shouldReceive('hasOpenReplacement')->once()->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertTrue($result->canRequestReplacement);
        $this->assertNull($result->blockedReason);
    }

    private function makeSubscription(
        int $siteId = 1,
        string $status = 'active',
        string $deliveryType = 'print',
        ?int $planId = 123,
    ): object {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->site_id = $siteId;
        $subscription->status = $status;
        $subscription->delivery_type = $deliveryType;
        $subscription->plan_id = $planId;

        return $subscription;
    }

    private function makeReplacement(int $issueDeliveryId): object
    {
        $replacement = Mockery::mock(FulfilmentReplacement::class)->makePartial();
        $replacement->issue_delivery_id = $issueDeliveryId;

        return $replacement;
    }

    private function stubActiveSubscription(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->andReturn($this->makeSubscription());
    }

    private function stubIssueExistsAndDispatched(): void
    {
        $this->replacementRepository->shouldReceive('issueExistsForSubscriptionPlan')->andReturn(true);
        $this->replacementRepository->shouldReceive('issueDeliveryWasDispatchedForSubscriptionPlan')->with(Mockery::any(), 123)->andReturn(true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->replacementRepository = Mockery::mock(FulfilmentReplacementRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->service = new FulfilmentReplacementEligibilityService(
            $this->subscriptionRepository,
            $this->replacementRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
