<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\SuspensionReason;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SuspensionReasonAdminControllerTest extends FunctionalTestCase
{
    public function test_index_lists_suspension_reasons(): void
    {
        SuspensionReason::create([
            'code' => 'seeded_suspension_reason',
            'label' => 'Seeded suspension reason',
            'requires_note' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response = $this->getForSite('/api/admin/suspension-reasons');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);
    }

    public function test_crud_lifecycle_for_suspension_reason(): void
    {
        $create = $this->postForSite('/api/admin/suspension-reasons', [
            'code' => 'functional_suspension_reason',
            'label' => 'Functional suspension reason',
            'requires_note' => true,
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $this->assertResponseStatus(201, $create);
        $created = json_decode($create->getContent(), true);
        $id = $created['id'];
        $this->assertEquals('functional_suspension_reason', $created['code']);

        $show = $this->getForSite('/api/admin/suspension-reasons/' . $id);
        $this->assertResponseStatus(200, $show);

        $update = $this->putForSite('/api/admin/suspension-reasons/' . $id, [
            'label' => 'Updated suspension reason',
        ]);
        $this->assertResponseStatus(200, $update);
        $updated = json_decode($update->getContent(), true);
        $this->assertEquals('Updated suspension reason', $updated['label']);

        $delete = $this->deleteForSite('/api/admin/suspension-reasons/' . $id);
        $this->assertResponseStatus(200, $delete);

        $reason = SuspensionReason::find($id);
        $this->assertNotNull($reason);
        $this->assertFalse((bool) $reason->is_active);
    }

    public function test_show_returns_404_for_unknown_reason(): void
    {
        $response = $this->getForSite('/api/admin/suspension-reasons/999999');
        $this->assertResponseStatus(404, $response);
    }
}
