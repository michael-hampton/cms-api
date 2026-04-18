<?php

namespace App\Tests\Functional\Controllers\Cms;

use App\Models\Campaign;
use App\Models\Subscriber;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CampaignControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_returns_campaigns_for_site(): void
    {
        $newsletter = $this->createNewsletter();

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Campaign 1',
            'slug' => 'campaign-1',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Campaign 2',
            'slug' => 'campaign-2',
            'newsletter_id' => $newsletter->id,
            'is_active' => false
        ]);

        $response = $this->getForSite('/api/campaigns');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function test_index_includes_stats(): void
    {
        $newsletter = $this->createNewsletter();

        // Active (is_active true and within date range)
        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Active Campaign',
            'slug' => 'active-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        // Inactive
        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Inactive Campaign',
            'slug' => 'inactive-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => false,
            'status' => 'paused',
        ]);

        $response = $this->getForSite('/api/campaigns');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('total', $data['stats']);
        $this->assertArrayHasKey('active', $data['stats']);
        $this->assertArrayHasKey('approved', $data['stats']);

        $this->assertEquals(2, $data['stats']['total']);
    }

    public function test_index_stats_count_approved_campaigns(): void
    {
        $newsletter = $this->createNewsletter();

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Approved 1',
            'slug' => 'approved-1',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Approved 2',
            'slug' => 'approved-2',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Pending',
            'slug' => 'pending-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => false,
            'status' => 'paused',
        ]);

        $response = $this->getForSite('/api/campaigns');

        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);
        $this->assertEquals(3, $data['stats']['total']);
        $this->assertEquals(2, $data['stats']['approved']);
    }

    public function test_index_returns_empty_stats_when_no_campaigns(): void
    {
        $response = $this->getForSite('/api/campaigns');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);
        $this->assertEquals(0, $data['stats']['total']);
        $this->assertEquals(0, $data['stats']['active']);
        $this->assertEquals(0, $data['stats']['approved']);
    }

    public function test_show_returns_campaign_with_stats(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'campaign_type' => 'email',
            'campaign_id' => 'TEST123',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'status' => 'active'
        ]);

        $response = $this->getForSite("/api/campaigns/{$campaign->id}");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('campaign', $data);
        $this->assertEquals('Test Campaign', $data['campaign']['name']);
        $this->assertEquals('TEST123', $data['campaign']['campaign_id']);
        $this->assertArrayHasKey('subscriber_count', $data['campaign']);
    }

    public function test_show_returns_404_for_nonexistent_campaign(): void
    {
        $response = $this->getForSite('/api/campaigns/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_create_campaign_with_valid_data(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite('/api/campaigns', [
            'name' => 'New Campaign',
            'slug' => 'new-campaign',
            'description' => 'Test description',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'gates_premium_content' => false,
            'site_id' => $this->siteId,
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('New Campaign', $data['data']['campaign']['name']);
        $this->assertEquals('new-campaign', $data['data']['campaign']['slug']);

        // Verify in database
        $campaign = Campaign::findBySlug('new-campaign', $this->siteId);
        $this->assertNotNull($campaign);
    }

    public function test_create_campaign_validates_date_range(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite('/api/campaigns', [
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'campaign_type' => 'email',
            'newsletter_id' => $newsletter->id,
            'start_date' => '2024-12-31',
            'end_date' => '2024-01-01',
            'site_id' => $this->siteId,
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('end_date', $data['errors']);
    }

    public function test_create_campaign_requires_name(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite('/api/campaigns', [
            'slug' => 'test-slug',
            'campaign_type' => 'email',
            'newsletter_id' => $newsletter->id
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function test_create_campaign_rejects_duplicate_slug(): void
    {
        $newsletter = $this->createNewsletter();

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Existing',
            'slug' => 'duplicate',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        $response = $this->postForSite('/api/campaigns', [
            'name' => 'New Campaign',
            'slug' => 'duplicate',
            'newsletter_id' => $newsletter->id
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('slug', $data['errors']);
    }

    public function test_update_campaign(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Original Name',
            'slug' => 'original',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        $response = $this->putForSite("/api/campaigns/{$campaign->id}", [
            'name' => 'Updated Name',
            'is_active' => false
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Name', $data['data']['campaign']['name']);
        $this->assertFalse($data['data']['campaign']['is_active']);
    }

    public function test_pause_campaign(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Active Campaign',
            'slug' => 'active',
            'campaign_type' => 'email',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'status' => 'active'
        ]);

        $response = $this->postForSite("/api/campaigns/{$campaign->id}/pause", []);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('paused', $data['data']['campaign']['status']);
        $this->assertFalse($data['data']['campaign']['is_active']);

        // Verify in database
        $updated = Campaign::find($campaign->id);
        $this->assertEquals('paused', $updated->status);
        $this->assertFalse($updated->is_active);
    }

    public function test_resume_campaign(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Paused Campaign',
            'slug' => 'paused',
            'campaign_type' => 'email',
            'newsletter_id' => $newsletter->id,
            'is_active' => false,
            'status' => 'paused'
        ]);

        $response = $this->postForSite("/api/campaigns/{$campaign->id}/resume", []);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('active', $data['data']['campaign']['status']);
        $this->assertTrue($data['data']['campaign']['is_active']);

        // Verify in database
        $updated = Campaign::find($campaign->id);
        $this->assertEquals('active', $updated->status);
        $this->assertTrue($updated->is_active);
    }

    public function test_resume_campaign_returns_404_for_nonexistent(): void
    {
        $response = $this->postForSite('/api/campaigns/99999/resume', []);

        $this->assertResponseStatus(404, $response);
    }

    public function test_delete_campaign_without_subscribers(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'To Delete',
            'slug' => 'delete-me',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        $response = $this->deleteForSite("/api/campaigns/{$campaign->id}");

        $this->assertResponseStatus(200, $response);

        $deleted = Campaign::find($campaign->id);
        $this->assertNull($deleted);
    }

    public function test_delete_campaign_with_subscribers_fails(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Has Subscribers',
            'slug' => 'has-subs',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        Subscriber::create([
            'email' => 'test@example.com',
            'campaign_id' => $campaign->id,
            'newsletter_id' => $newsletter->id,
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'confirmation_token' => 'token',
            'unsubscribe_token' => 'unsub'
        ]);

        $response = $this->deleteForSite("/api/campaigns/{$campaign->id}");

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cannot delete campaign', $data['error']);
    }

    public function test_clone_campaign(): void
    {
        $newsletter = $this->createNewsletter();

        $original = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Original',
            'slug' => 'original',
            'description' => 'Original description',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'gates_premium_content' => true
        ]);

        $response = $this->postForSite("/api/campaigns/{$original->id}/clone", []);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $cloned = $data['data']['campaign'];

        $this->assertStringContainsString('Copy', $cloned['name']);
        $this->assertEquals('original-1', $cloned['slug']);
        $this->assertFalse($cloned['is_active']);
        $this->assertNull($cloned['newsletter_id']);
    }

    public function test_get_active_campaigns(): void
    {
        $newsletter = $this->createNewsletter();

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Active',
            'slug' => 'active',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Inactive',
            'slug' => 'inactive',
            'newsletter_id' => $newsletter->id,
            'is_active' => false
        ]);

        $response = $this->getForSite('/api/campaigns/active');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('active', $data['data'][0]['slug']);
    }
}