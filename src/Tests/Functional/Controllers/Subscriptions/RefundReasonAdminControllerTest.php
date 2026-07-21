<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\RefundReason;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class RefundReasonAdminControllerTest extends FunctionalTestCase
{
    public function test_index_lists_refund_reasons(): void
    {
        RefundReason::create([
            'code' => 'seeded_refund_reason',
            'label' => 'Seeded refund reason',
            'requires_note' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response = $this->getForSite('/api/admin/refund-reasons');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);
    }

    public function test_crud_lifecycle_for_refund_reason(): void
    {
        $create = $this->postForSite('/api/admin/refund-reasons', [
            'code' => 'functional_refund_reason',
            'label' => 'Functional refund reason',
            'requires_note' => true,
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $this->assertResponseStatus(201, $create);
        $created = json_decode($create->getContent(), true);
        $id = $created['id'];
        $this->assertEquals('functional_refund_reason', $created['code']);

        $show = $this->getForSite('/api/admin/refund-reasons/' . $id);
        $this->assertResponseStatus(200, $show);

        $update = $this->putForSite('/api/admin/refund-reasons/' . $id, [
            'label' => 'Updated refund reason',
        ]);
        $this->assertResponseStatus(200, $update);
        $updated = json_decode($update->getContent(), true);
        $this->assertEquals('Updated refund reason', $updated['label']);

        $delete = $this->deleteForSite('/api/admin/refund-reasons/' . $id);
        $this->assertResponseStatus(200, $delete);

        $reason = RefundReason::find($id);
        $this->assertNotNull($reason);
        $this->assertFalse((bool) $reason->is_active);
    }

    public function test_show_returns_404_for_unknown_reason(): void
    {
        $response = $this->getForSite('/api/admin/refund-reasons/999999');
        $this->assertResponseStatus(404, $response);
    }
}
