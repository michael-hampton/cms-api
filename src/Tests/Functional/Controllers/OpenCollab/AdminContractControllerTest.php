<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\Contract;
use App\Models\UserContractSignature;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminContractControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_returns_all_contracts_for_site(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'First contract content here for testing.', 'created_at' => date('Y-m-d H:i:s')]);
        Contract::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Second contract content here for testing.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite('/api/open-collab/admin/contracts');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], static fn($key) => is_int($key), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $items);
        $this->assertEquals(2, $items[0]['version']);
    }

    public function test_latest_returns_highest_version_contract(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Old contract version content.', 'created_at' => date('Y-m-d H:i:s')]);
        Contract::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Newest contract version content.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite('/api/open-collab/admin/contracts/latest');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['data']['contract']['version']);
    }

    public function test_latest_returns_404_when_no_contracts_exist(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/contracts/latest');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_show_returns_contract_by_id(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Full contract content for viewing.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite("/api/open-collab/admin/contracts/{$contract->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($contract->id, $data['data']['contract']['id']);
        $this->assertEquals('Full contract content for viewing.', $data['data']['contract']['content']);
    }

    public function test_show_returns_404_for_contract_on_different_site(): void
    {
        $otherSite = \App\Models\Site::create(['name' => 'Other', 'slug' => 'other-contract-test', 'is_default' => false]);
        $contract = Contract::create(['site_id' => $otherSite->id, 'version' => 1, 'content' => 'Other site contract.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite("/api/open-collab/admin/contracts/{$contract->id}");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_store_creates_contract_with_auto_incremented_version(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Existing v1 contract.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->postForSite('/api/open-collab/admin/contracts', [
            'content' => 'This is the new version two contract with enough content to pass validation.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(2, $data['data']['contract']['version']);
        $this->assertDatabaseHas('oc_contracts', ['site_id' => $this->siteId, 'version' => 2]);
    }

    public function test_store_creates_version_1_when_no_contracts_exist(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/contracts', [
            'content' => 'Brand new contract for a fresh site with enough content.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(1, $data['data']['contract']['version']);
    }

    public function test_store_returns_422_when_content_is_empty(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/contracts', ['content' => '']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_422_when_content_is_too_short(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/contracts', ['content' => 'Too short.']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_content_of_unsigned_contract(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Original content here for testing purposes only.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->putForSite("/api/open-collab/admin/contracts/{$contract->id}", [
            'content' => 'Updated contract content that is long enough to pass validation checks.',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Updated', $data['data']['contract']['content']);
        $this->assertDatabaseHas('oc_contracts', ['id' => $contract->id, 'content' => 'Updated contract content that is long enough to pass validation checks.']);
    }

    public function test_update_returns_409_when_contract_has_been_signed(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Signed contract content for testing purposes.', 'created_at' => date('Y-m-d H:i:s')]);
        UserContractSignature::create([
            'user_id' => $this->authenticatedUser->id,
            'contract_id' => $contract->id,
            'signed_at' => date('Y-m-d H:i:s'),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->putForSite("/api/open-collab/admin/contracts/{$contract->id}", [
            'content' => 'Attempting to update a signed contract which should be blocked.',
        ]);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function test_update_returns_422_when_content_too_short(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Original content here for testing purposes only.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->putForSite("/api/open-collab/admin/contracts/{$contract->id}", ['content' => 'Too short.']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_update_returns_404_for_wrong_site(): void
    {
        $otherSite = \App\Models\Site::create(['name' => 'Other', 'slug' => 'other-contract-upd', 'is_default' => false]);
        $contract = Contract::create(['site_id' => $otherSite->id, 'version' => 1, 'content' => 'Other site content.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->putForSite("/api/open-collab/admin/contracts/{$contract->id}", [
            'content' => 'Trying to update another sites contract, which should fail here.',
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_destroy_deletes_latest_unsigned_contract(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Contract to delete for testing purposes here.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->deleteForSite("/api/open-collab/admin/contracts/{$contract->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('oc_contracts', ['id' => $contract->id]);
    }

    public function test_destroy_returns_409_when_not_latest_version(): void
    {
        $v1 = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Version one contract content for site.', 'created_at' => date('Y-m-d H:i:s')]);
        Contract::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Version two contract content for site.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->deleteForSite("/api/open-collab/admin/contracts/{$v1->id}");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contracts', ['id' => $v1->id]);
    }

    public function test_destroy_returns_409_when_contract_has_been_signed(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Signed contract, cannot delete this one.', 'created_at' => date('Y-m-d H:i:s')]);
        UserContractSignature::create([
            'user_id' => $this->authenticatedUser->id,
            'contract_id' => $contract->id,
            'signed_at' => date('Y-m-d H:i:s'),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->deleteForSite("/api/open-collab/admin/contracts/{$contract->id}");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contracts', ['id' => $contract->id]);
    }

    public function test_destroy_returns_404_for_wrong_site(): void
    {
        $otherSite = \App\Models\Site::create(['name' => 'Other', 'slug' => 'other-contract-del', 'is_default' => false]);
        $contract = Contract::create(['site_id' => $otherSite->id, 'version' => 1, 'content' => 'Other site contract content here.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->deleteForSite("/api/open-collab/admin/contracts/{$contract->id}");
        $this->assertEquals(404, $response->getStatusCode());
    }
}