<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\CancellationReason;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CancellationReasonAdminControllerTest extends FunctionalTestCase
{
    public function test_index_lists_cancellation_reasons(): void
    {
        $response = $this->getForSite('/api/admin/cancellation-reasons');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);
        $this->assertArrayHasKey('code', $data['data'][0]);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_crud_lifecycle_for_cancellation_reason(): void
    {
        $create = $this->postForSite('/api/admin/cancellation-reasons', [
            'code' => 'functional_cancel_reason',
            'label' => 'Functional cancel reason',
            'requires_note' => true,
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $this->assertResponseStatus(201, $create);
        $created = json_decode($create->getContent(), true);
        $id = $created['id'];
        $this->assertEquals('functional_cancel_reason', $created['code']);
        $this->assertTrue($created['requires_note']);

        $show = $this->getForSite('/api/admin/cancellation-reasons/' . $id);
        $this->assertResponseStatus(200, $show);
        $shown = json_decode($show->getContent(), true);
        $this->assertEquals('Functional cancel reason', $shown['label']);

        $update = $this->putForSite('/api/admin/cancellation-reasons/' . $id, [
            'label' => 'Updated cancel reason',
            'requires_note' => false,
        ]);
        $this->assertResponseStatus(200, $update);
        $updated = json_decode($update->getContent(), true);
        $this->assertEquals('Updated cancel reason', $updated['label']);
        $this->assertFalse($updated['requires_note']);

        $delete = $this->deleteForSite('/api/admin/cancellation-reasons/' . $id);
        $this->assertResponseStatus(200, $delete);

        $reason = CancellationReason::find($id);
        $this->assertNotNull($reason);
        $this->assertFalse((bool) $reason->is_active);
    }

    public function test_show_returns_404_for_unknown_reason(): void
    {
        $response = $this->getForSite('/api/admin/cancellation-reasons/999999');
        $this->assertResponseStatus(404, $response);
    }
}
