<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Models\Guideline;
use App\Models\Site;
use App\Models\UserGuidelinesAcknowledgement;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminGuidelinesControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_returns_all_guideline_versions_for_site(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'First guidelines version content for testing.', 'created_at' => date('Y-m-d H:i:s'), 'status' => GuidelineStatus::Published->value]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Second guidelines version content for testing.', 'created_at' => date('Y-m-d H:i:s'), 'status' => GuidelineStatus::Published->value]);

        $response = $this->getForSite('/api/open-collab/admin/guidelines');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], static fn($key) => is_int($key), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $items);
        $this->assertEquals(2, $items[0]['version']);
    }

    public function test_latest_returns_highest_version_guideline(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Original brand guidelines content here.', 'created_at' => date('Y-m-d H:i:s'), 'status' => GuidelineStatus::Published->value]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Updated brand guidelines content here.', 'created_at' => date('Y-m-d H:i:s'), 'status' => GuidelineStatus::Published->value]);

        $response = $this->getForSite('/api/open-collab/admin/guidelines/latest');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['data']['guideline']['version']);
    }

    public function test_latest_returns_404_when_no_guidelines_exist(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/guidelines/latest');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_show_returns_guideline_by_id(): void
    {
        $guideline = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Full guidelines content for individual viewing.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite("/api/open-collab/admin/guidelines/{$guideline->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($guideline->id, $data['data']['guideline']['id']);
    }

    public function test_show_returns_404_for_guideline_on_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-guidelines-test', 'is_default' => false]);
        $guideline = Guideline::create(['site_id' => $otherSite->id, 'version' => 1, 'content' => 'Other site guidelines content.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite("/api/open-collab/admin/guidelines/{$guideline->id}");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_store_creates_guideline_with_auto_incremented_version(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Existing v1 guidelines content.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->postForSite('/api/open-collab/admin/guidelines', [
            'content' => 'This is the updated version two guidelines with sufficient content to pass validation.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(2, $data['data']['guideline']['version']);
        $this->assertDatabaseHas('oc_guidelines', ['site_id' => $this->siteId, 'version' => 2]);
    }

    public function test_store_creates_version_1_when_no_guidelines_exist(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/guidelines', [
            'content' => 'Brand new guidelines for a fresh site with enough content to be valid.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(1, $data['data']['guideline']['version']);
    }

    public function test_store_updates_site_guidelines_version_column(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/guidelines', [
            'content' => 'New guidelines that should bump the site guidelines_version pointer.',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('sites', ['id' => $this->siteId, 'guidelines_version' => 1]);
    }

    public function test_store_returns_422_when_content_is_empty(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/guidelines', ['content' => '']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_422_when_content_is_too_short(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/guidelines', ['content' => 'Too short.']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_content_of_unacknowledged_guideline(): void
    {
        $guideline = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Original guidelines content here for testing purposes.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->putForSite("/api/open-collab/admin/guidelines/{$guideline->id}", [
            'content' => 'Updated guidelines content that is long enough to pass the validation requirements.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Updated', $data['data']['guideline']['content']);
        $this->assertDatabaseHas('oc_guidelines', ['id' => $guideline->id, 'content' => 'Updated guidelines content that is long enough to pass the validation requirements.']);
    }

    public function test_update_returns_409_when_guideline_has_been_acknowledged(): void
    {
        $guideline = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Guidelines acknowledged by contributor already signed.', 'created_at' => date('Y-m-d H:i:s')]);
        UserGuidelinesAcknowledgement::create([
            'user_id' => $this->authenticatedUser->id,
            'site_id' => $this->siteId,
            'version' => 1,
            'acknowledged_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->putForSite("/api/open-collab/admin/guidelines/{$guideline->id}", [
            'content' => 'Trying to update acknowledged guidelines which should be blocked here.',
        ]);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function test_update_returns_422_when_content_too_short(): void
    {
        $guideline = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Original guidelines content here for testing.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->putForSite("/api/open-collab/admin/guidelines/{$guideline->id}", ['content' => 'Short.']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_update_returns_404_for_wrong_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-guide-upd', 'is_default' => false]);
        $guideline = Guideline::create(['site_id' => $otherSite->id, 'version' => 1, 'content' => 'Other site guidelines content.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->putForSite("/api/open-collab/admin/guidelines/{$guideline->id}", [
            'content' => 'Trying to update another sites guidelines which should fail safely.',
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_destroy_deletes_latest_unacknowledged_guideline(): void
    {
        $guideline = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Guidelines to delete for testing purposes only.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->deleteForSite("/api/open-collab/admin/guidelines/{$guideline->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('oc_guidelines', ['id' => $guideline->id]);
    }

    public function test_destroy_returns_409_when_not_latest_version(): void
    {
        $v1 = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Version one guidelines content for site.', 'created_at' => date('Y-m-d H:i:s')]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Version two guidelines content for site.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->deleteForSite("/api/open-collab/admin/guidelines/{$v1->id}");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertDatabaseHas('oc_guidelines', ['id' => $v1->id]);
    }

    public function test_destroy_returns_409_when_guideline_has_been_acknowledged(): void
    {
        $guideline = Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'Acknowledged guidelines that cannot be deleted here.', 'created_at' => date('Y-m-d H:i:s')]);
        UserGuidelinesAcknowledgement::create([
            'user_id' => $this->authenticatedUser->id,
            'site_id' => $this->siteId,
            'version' => 1,
            'acknowledged_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->deleteForSite("/api/open-collab/admin/guidelines/{$guideline->id}");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertDatabaseHas('oc_guidelines', ['id' => $guideline->id]);
    }

    public function test_destroy_rolls_back_site_guidelines_version_pointer(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'First version guidelines content here.', 'created_at' => date('Y-m-d H:i:s')]);
        $v2 = Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'Second version guidelines content here.', 'created_at' => date('Y-m-d H:i:s')]);

        $this->deleteForSite("/api/open-collab/admin/guidelines/{$v2->id}");

        $this->assertDatabaseHas('sites', ['id' => $this->siteId, 'guidelines_version' => 1]);
    }

    public function test_destroy_returns_404_for_wrong_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-guide-del', 'is_default' => false]);
        $guideline = Guideline::create(['site_id' => $otherSite->id, 'version' => 1, 'content' => 'Other site guidelines content.', 'created_at' => date('Y-m-d H:i:s')]);

        $response = $this->deleteForSite("/api/open-collab/admin/guidelines/{$guideline->id}");
        $this->assertEquals(404, $response->getStatusCode());
    }
}