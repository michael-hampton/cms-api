<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class IssueDeliveryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // Helpers
    // =========================================================================

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
            'type' => 'paid',
        ]);

        return IssueDelivery::create(array_merge([
            'site_id' => $this->siteId,
            'issue_title' => 'Test Issue',
            'issue_number' => '001',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +5 days')),
            'status' => IssueScheduleStatus::DRAFT->value,
            'subscription_id' => $subscription->id,
        ], $overrides));
    }

    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'issue_title' => 'Test Issue',
            'issue_number' => '001',
            'on_sale_date' => '2025-03-01',
            'cut_off_date' => '2025-02-25',
            'status' => 'draft',
        ], $overrides);
    }

    private function validUpdatePayload(array $overrides = []): array
    {
        return array_merge([
            'issue_title' => 'Updated Title',
            'issue_number' => 1
        ], $overrides);
    }

    // =========================================================================
    // GET /api/issue-deliveries
    // =========================================================================

    public function testIndexDisplaysSchedules(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->getForSite('/api/issue-deliveries');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString($schedule->issue_title, $content);
    }

    // =========================================================================
    // POST /api/issue-deliveries — happy path
    // =========================================================================

    public function testStoreCreatesSchedule(): void
    {
        $response = $this->postForSite('/api/issue-deliveries', $this->validStorePayload());

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

    // =========================================================================
    // POST — StoreIssueDeliveryRequest validation
    // =========================================================================

    public function testStoreRequiresIssueTitle(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['issue_title' => null])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRequiresIssueNumber(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['issue_number' => null])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRequiresOnSaleDate(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['on_sale_date' => null])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsIssueTitleExceeding255Characters(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['issue_title' => str_repeat('x', 256)])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsIssueNumberExceeding100Characters(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['issue_number' => str_repeat('x', 101)])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsIssueConcodeExceeding100Characters(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['issue_code' => str_repeat('x', 101)])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsInvalidOnSaleDateFormat(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['on_sale_date' => 'not-a-date'])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsInvalidCutOffDateFormat(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['cut_off_date' => 'not-a-date'])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsInvalidFulfilmentDateFormat(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['fulfilment_date' => 'not-a-date'])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsStatusNotInAllowedValues(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['status' => 'pending']) // not in: draft,active,cancelled
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[DataProvider('validStatuses')]
    public function testStoreAcceptsAllValidStatuses(string $status): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['status' => $status])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public static function validStatuses(): array
    {
        return [
            ['draft'],
            ['active'],
            ['cancelled'],
        ];
    }

    public function testStoreRejectsNonIntegerSubscriptionPlanId(): void
    {
        $response = $this->postForSite(
            '/api/issue-deliveries',
            $this->validStorePayload(['subscription_plan_id' => 'not-an-int'])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreValidatesEmptyPayload(): void
    {
        $response = $this->postForSite('/api/issue-deliveries', []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // PUT /api/issue-deliveries/{id} — happy path
    // =========================================================================

    public function testUpdateSchedule(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            [
                'issue_title' => 'Updated Title',
                'status' => 'active',
                'issue_number' => 1,
                'label' => 'test',
                'on_sale_date' => now()
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Updated Title', $responseData['data']['issue_title']);

        $schedule = $schedule->fresh();
        $this->assertEquals('Updated Title', $schedule->issue_title);
        $this->assertEquals(IssueScheduleStatus::ACTIVE->value, $schedule->status);
    }

    public function testUpdateAllowsNullableFieldsToBeNull(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            [
                'issue_title' => 'Updated Title',
                'issue_number' => 1,
                'issue_code' => null,
                'subscription_plan_id' => null,
                'on_sale_date' => now(),
                'cut_off_date' => null,
                'fulfilment_date' => null,
                'notes' => null,
                'status' => 'active'
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateRequiresIssueTitle(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['status' => 'active']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsNullIssueTitle(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['issue_title' => null]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsEmptyIssueTitle(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['issue_title' => '']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateValidatesEmptyPayload(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            []
        );

        $this->assertEquals(422, $response->getStatusCode());
    }


    // =========================================================================
    // PUT — UpdateIssueDeliveryRequest validation
    // =========================================================================

    public function testUpdateRejectsIssueTitleExceeding255Characters(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['issue_title' => str_repeat('x', 256)]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsIssueNumberExceeding100Characters(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['issue_number' => str_repeat('x', 101)]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsStatusNotInAllowedValues(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['status' => 'pending']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsInvalidOnSaleDateFormat(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['on_sale_date' => 'not-a-date']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsInvalidCutOffDateFormat(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['cut_off_date' => 'not-a-date']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsNonIntegerSubscriptionPlanId(): void
    {
        $schedule = $this->createIssueSchedule();

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            ['subscription_plan_id' => 'not-an-int']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateAcceptsPartialPayload(): void
    {
        $schedule = $this->createIssueSchedule();

        // UpdateIssueDeliveryRequest has no required fields — partial update is valid
        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}",
            [
                'issue_title' => 'Partial Update',
                'issue_number' => 1,
                'status' => 'active',
                'on_sale_date' => now()
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Partial Update', $schedule->fresh()->issue_title);
    }

    // =========================================================================
    // DELETE /api/issue-deliveries/{id}
    // =========================================================================

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
            'status' => IssueScheduleStatus::ACTIVE->value,
        ]);

        $response = $this->deleteForSite("/api/issue-deliveries/{$schedule->id}");

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('existing deliveries', $responseData['message']);
    }

    // =========================================================================
    // PUT /api/issue-deliveries/{id}/status
    // =========================================================================

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
        $this->assertEquals(IssueScheduleStatus::ACTIVE->value, $schedule->fresh()->status);
    }

    public function testUpdateStatusRejectsInvalidStatus(): void
    {
        $schedule = $this->createIssueSchedule(['status' => IssueScheduleStatus::DRAFT->value]);

        $response = $this->putForSite(
            "/api/issue-deliveries/{$schedule->id}/status",
            ['status' => 'invalid']
        );

        $this->assertEquals(500, $response->getStatusCode());
    }

    // =========================================================================
    // GET /api/issue-deliveries/search
    // =========================================================================

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
}