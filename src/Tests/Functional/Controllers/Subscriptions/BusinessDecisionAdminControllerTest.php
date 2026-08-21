<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\CancellationReason;
use App\Models\RefundReason;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BusinessDecisionAdminControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_and_crud_for_business_decision(): void
    {
        $create = $this->postForSite('/api/admin/business-decisions', [
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Functional cancellations decision',
            'description' => 'Created by functional test',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->assertResponseStatus(201, $create);
        $created = json_decode($create->getContent(), true);
        $id = $created['id'];
        $this->assertEquals('cancellations', $created['category']);

        $index = $this->getForSite('/api/admin/business-decisions?category=cancellations');
        $this->assertResponseStatus(200, $index);
        $listed = json_decode($index->getContent(), true);
        $this->assertTrue($listed['success']);
        $this->assertNotEmpty($listed['data']);

        $show = $this->getForSite('/api/admin/business-decisions/' . $id);
        $this->assertResponseStatus(200, $show);

        $update = $this->putForSite('/api/admin/business-decisions/' . $id, [
            'name' => 'Updated cancellations decision',
        ]);
        $this->assertResponseStatus(200, $update);
        $updated = json_decode($update->getContent(), true);
        $this->assertEquals('Updated cancellations decision', $updated['name']);
    }

    public function test_index_filters_by_created_at_range(): void
    {
        $inRange = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'In Range Decision',
            'is_default' => false,
            'is_active' => true,
        ]);
        $outOfRange = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Out Of Range Decision',
            'is_default' => false,
            'is_active' => true,
        ]);

        BusinessDecision::where('id', $inRange->id)->update(['created_at' => '2026-03-15 10:00:00']);
        BusinessDecision::where('id', $outOfRange->id)->update(['created_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/admin/business-decisions?date_from=2026-03-01&date_to=2026-03-31');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $names = array_column($data['data'], 'name');
        $this->assertContains('In Range Decision', $names);
        $this->assertNotContains('Out Of Range Decision', $names);
    }

    public function test_index_filters_by_updated_at_range(): void
    {
        $inRange = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Recently Updated Decision',
            'is_default' => false,
            'is_active' => true,
        ]);
        $outOfRange = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Stale Decision',
            'is_default' => false,
            'is_active' => true,
        ]);

        BusinessDecision::where('id', $inRange->id)->update(['updated_at' => '2026-03-15 10:00:00']);
        BusinessDecision::where('id', $outOfRange->id)->update(['updated_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/admin/business-decisions?updated_from=2026-03-01&updated_to=2026-03-31');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $names = array_column($data['data'], 'name');
        $this->assertContains('Recently Updated Decision', $names);
        $this->assertNotContains('Stale Decision', $names);
    }

    public function test_assign_decision_to_plan(): void
    {
        $plan = $this->createSubscriptionPlan();
        $decision = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Assignable decision',
            'is_default' => false,
            'is_active' => true,
        ]);

        $response = $this->postForSite('/api/admin/business-decisions/assign', [
            'assignable_type' => 'plan',
            'assignable_id' => $plan->id,
            'business_decision_id' => $decision->id,
        ]);

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($decision->id, $data['business_decision_id']);
        $this->assertEquals($plan->id, $data['assignable_id']);
        $this->assertEquals('cancellations', $data['category']);
    }

    public function test_list_and_upsert_cancellation_reason_policy(): void
    {
        $decision = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Policy decision',
            'is_default' => false,
            'is_active' => true,
        ]);
        $reason = CancellationReason::query()->where('is_active', true)->first()
            ?? CancellationReason::create([
                'code' => 'policy_cancel_reason',
                'label' => 'Policy cancel reason',
                'requires_note' => false,
                'is_active' => true,
                'sort_order' => 10,
            ]);

        $list = $this->getForSite('/api/admin/business-decisions/' . $decision->id . '/reason-policies');
        $this->assertResponseStatus(200, $list);

        $upsert = $this->putForSite('/api/admin/business-decisions/' . $decision->id . '/reason-policies', [
            'cancellation_reason_id' => $reason->id,
            'allow_cancel' => true,
            'refund_max_percent' => 50,
            'show_save_actions' => false,
        ]);
        $this->assertResponseStatus(200, $upsert);
        $policy = json_decode($upsert->getContent(), true);
        $this->assertEquals(50, $policy['refund_max_percent']);
        $this->assertTrue($policy['allow_cancel']);
    }

    public function test_list_and_upsert_refund_reason_policy(): void
    {
        $decision = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::REFUNDS->value,
            'name' => 'Refunds decision',
            'is_default' => false,
            'is_active' => true,
        ]);
        $reason = RefundReason::create([
            'code' => 'policy_refund_reason',
            'label' => 'Policy refund reason',
            'requires_note' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $list = $this->getForSite('/api/admin/business-decisions/' . $decision->id . '/refund-reason-policies');
        $this->assertResponseStatus(200, $list);

        $upsert = $this->putForSite('/api/admin/business-decisions/' . $decision->id . '/refund-reason-policies', [
            'refund_reason_id' => $reason->id,
            'allow_full' => true,
            'allow_manual' => true,
            'refund_max_percent' => 75,
            'default_notify_customer' => true,
        ]);
        $this->assertResponseStatus(200, $upsert);
        $policy = json_decode($upsert->getContent(), true);
        $this->assertEquals(75, $policy['refund_max_percent']);
        $this->assertTrue($policy['allow_full']);
    }

    public function test_get_and_upsert_suspension_policy(): void
    {
        $decision = BusinessDecision::create([
            'category' => BusinessDecisionCategoryEnum::SUSPENSIONS->value,
            'name' => 'Suspensions decision',
            'is_default' => false,
            'is_active' => true,
        ]);

        $upsert = $this->putForSite('/api/admin/business-decisions/' . $decision->id . '/suspension-policy', [
            'allow_suspend' => true,
            'requires_note' => false,
        ]);
        $this->assertResponseStatus(200, $upsert);
        $policy = json_decode($upsert->getContent(), true);
        $this->assertTrue($policy['allow_suspend']);
        $this->assertFalse($policy['requires_note']);

        $get = $this->getForSite('/api/admin/business-decisions/' . $decision->id . '/suspension-policy');
        $this->assertResponseStatus(200, $get);
        $loaded = json_decode($get->getContent(), true);
        $this->assertNotNull($loaded['data']);
        $this->assertTrue($loaded['data']['allow_suspend']);
        $this->assertFalse($loaded['data']['requires_note']);
    }

    public function test_show_returns_404_for_unknown_decision(): void
    {
        $response = $this->getForSite('/api/admin/business-decisions/999999');
        $this->assertResponseStatus(404, $response);
    }
}
