<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class IssueDeliveryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexDisplaysSchedules(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->getForSite('/api/issue-deliveries');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString($schedule->issue_title, $content);
    }

    private function createIssueSchedule(array $overrides = []): IssueDelivery
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $subscription = \App\Models\Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price ?? 10.00,
            'currency' => $plan->currency ?? 'GBP',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'type' => 'paid'
        ]);

        return IssueDelivery::create(array_merge([
            'site_id' => $this->siteId,
            'issue_title' => 'Test Issue',
            'issue_number' => '001',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month + 5 days')),
            'status' => IssueScheduleStatus::DRAFT->value,
            'subscription_id' => $subscription->id,
        ], $overrides));
    }

    public function testStoreCreatesSchedule(): void
    {
        $data = [
            'issue_title' => 'Test Issue',
            'issue_number' => '001',
            'on_sale_date' => '2025-03-01',
            'cut_off_date' => '2025-02-25',
            'status' => 'draft'
        ];

        $response = $this->postForSite(
            '/api/issue-deliveries', $data);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('Test Issue', $responseData['data']['issue_title']);

        $schedule = IssueDelivery::where('issue_title', 'Test Issue')->first();
        $this->assertNotNull($schedule);
        $this->assertEquals('001', $schedule->issue_number);
        $this->assertEquals(IssueScheduleStatus::DRAFT->value, $schedule->status);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries', []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateSchedule(): void
    {
        $schedule = $this->createIssueSchedule();

        $data = [
            'issue_title' => 'Updated Title',
            'status' => 'active'
        ];

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}", $data);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Updated Title', $responseData['data']['issue_title']);

        $schedule = $schedule->fresh();
        $this->assertEquals('Updated Title', $schedule->issue_title);
        $this->assertEquals(IssueScheduleStatus::ACTIVE->value, $schedule->status);
    }

    public function testDestroyDeletesSchedule(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->deleteForSite("/api/issue-deliveries/{$schedule->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertNull(IssueDelivery::find($schedule->id));
    }

    public function testDestroyFailsWhenHasDeliveries(): void
    {
        $schedule = $this->createIssueSchedule();
        $subscriptionId = $schedule->subscription_id;

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscriptionId,
            'issue_number' => '1',
            'issue_title' => 'Test',
            'estimated_delivery_date' => date('Y-m-d'),
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        $response = $this->deleteForSite("/api/issue-deliveries/{$schedule->id}");

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('existing deliveries', $responseData['message']);
    }

    public function testUpdateStatus(): void
    {
        $schedule = $this->createIssueSchedule(['status' => IssueScheduleStatus::DRAFT->value]);

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}/status",
            ['status' => 'active']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);

        $schedule = $schedule->fresh();
        $this->assertEquals(IssueScheduleStatus::ACTIVE->value, $schedule->status);
    }

    public function testSearchSchedules(): void
    {
        $schedule1 = $this->createIssueSchedule(['issue_title' => 'January Issue', 'status' => IssueScheduleStatus::ACTIVE->value]);
        $schedule2 = $this->createIssueSchedule(['issue_title' => 'February Issue', 'status' => IssueScheduleStatus::DRAFT->value]);

        $response = $this->getForSite('/api/issue-deliveries/search?status=active');

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(1, $responseData['items']);
        $this->assertEquals('January Issue', $responseData['items'][0]['issue_title']);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}