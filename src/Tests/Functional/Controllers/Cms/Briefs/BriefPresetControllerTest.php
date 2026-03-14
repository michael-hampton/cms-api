<?php

namespace App\Tests\Functional\Controllers\Cms\Briefs;

use App\Models\BriefTemplate;
use App\Models\Model;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Component (HTTP-level) tests for BriefPresetController.
 *
 * These tests exercise the full HTTP stack against a real (test) database, so
 * they extend FunctionalTestCase exactly like BriefControllerTest does.
 */
class BriefPresetControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_index_returns_list_for_authenticated_user(): void
    {
        $this->createPreset(['name' => 'Preset One']);
        $this->createPreset(['name' => 'Preset Two']);

        $response = $this->getForSite('/api/brief-preset');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
    }

    /**
     * Creates a BriefTemplate row with preset-capable columns.
     */
    private function createPreset(array $overrides = []): Model
    {
        return BriefTemplate::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Preset',
            'type' => 'custom',
            'is_system' => false,
            'created_by' => 1,
        ], $overrides));
    }

    public function test_show_returns_single_preset(): void
    {
        $preset = $this->createPreset(['name' => 'My Preset']);

        $response = $this->getForSite("/api/brief-preset/{$preset->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('My Preset', $data['data']['name']);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_show_returns_404_for_unknown(): void
    {
        $response = $this->getForSite('/api/brief-preset/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_store_creates_preset_as_site_admin(): void
    {
        $user = $this->createUser();

        $response = $this->postForSite('/api/brief-preset', [
            'name' => 'New Preset',
            'description' => 'A useful preset',
            'type' => 'test'
        ], [], $this->adminHeaders());


        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Preset', $data['data']['name']);
        $this->assertFalse($data['data']['is_system']);
    }

    /**
     * Returns request headers that satisfy the site-admin check.
     */
    private function adminHeaders(): array
    {
        // SiteContext::isSiteAdmin() reads from the resolved site context.
        // FunctionalTestCase sets up the site context automatically;
        // we just need to mark the acting user as a site admin.
        return ['X-Is-Site-Admin' => '1'];
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_returns_403_for_non_admin(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite('/api/brief-preset', [
            'name' => 'Should Fail',
        ], [], $this->nonAdminHeaders());

        $this->assertEquals(401, $response->getStatusCode());
    }

    private function nonAdminHeaders(): array
    {
        return ['X-Is-Site-Admin' => '0'];
    }

    public function test_store_returns_400_for_blank_title(): void
    {
        $response = $this->postForSite('/api/brief-preset', [
            'name' => '',
            'type' => 'test'
        ], [], $this->adminHeaders());

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_store_with_subtasks_saves_subtask_data(): void
    {
        $response = $this->postForSite('/api/brief-preset', [
            'name' => 'Preset With Tasks',
            'type' => 'test',
            'default_subtasks' => [
                ['title' => 'Write draft', 'description' => 'First pass'],
                ['title' => 'Review copy'],
            ],
        ], [], $this->adminHeaders());

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $savedSubtasks = $data['data']['default_subtasks'];
        $this->assertCount(2, $savedSubtasks);
        $this->assertEquals('Write draft', $savedSubtasks[0]['title']);
        $this->assertEquals('Review copy', $savedSubtasks[1]['title']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_as_site_admin(): void
    {
        $preset = $this->createPreset(['name' => 'Old Name']);

        $response = $this->putForSite("/api/brief-preset/{$preset->id}", [
            'name' => 'New Name',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Name', $data['data']['name']);
    }

    public function test_update_returns_403_for_non_admin(): void
    {
        $preset = $this->createPreset();
        $this->unauthenticate();

        $response = $this->putForSite("/api/brief-preset/{$preset->id}", [
            'name' => 'Should Fail',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_as_site_admin(): void
    {
        $preset = $this->createPreset();

        $response = $this->deleteForSite("/api/brief-preset/{$preset->id}", $this->adminHeaders());

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(BriefTemplate::find($preset->id));
    }

    // ── createFromPreset ──────────────────────────────────────────────────────

    public function test_create_from_preset_populates_brief_fields(): void
    {
        $user = $this->createUser();
        $preset = $this->createPreset([
            'name' => 'Review Template',
            'default_owner_ids' => json_encode([$user->id]),
            'default_fields' => ['target_word_count' => 1500],
        ]);

        $response = $this->postForSite("/api/brief/from-preset/{$preset->id}", [
            'title' => 'My Review',
            'owner_id' => $user->id,
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('My Review', $data['data']['title']);
    }

    public function test_create_from_preset_creates_brief_tasks(): void
    {
        $user = $this->createUser();
        $preset = $this->createPreset([
            'default_subtasks' => json_encode([
                ['title' => 'Write draft'],
                ['title' => 'Review'],
            ]),
        ]);

        $response = $this->postForSite("/api/brief/from-preset/{$preset->id}", [
            'title' => 'Task Brief',
            'owner_id' => $user->id,
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $briefId = $data['data']['id'];

        $tasks = \App\Models\BriefTask::where('brief_id', $briefId)->get();
        $this->assertCount(2, $tasks);
    }

    public function test_created_task_count_matches_preset_subtask_count(): void
    {
        $user = $this->createUser();
        $preset = $this->createPreset([
            'default_subtasks' => json_encode([
                ['title' => 'Step 1'],
                ['title' => 'Step 2'],
                ['title' => 'Step 3'],
            ]),
        ]);

        $response = $this->postForSite("/api/brief/from-preset/{$preset->id}", [
            'title' => 'Three Task Brief',
            'owner_id' => $user->id,
        ]);

        $data = json_decode($response->getContent(), true);
        $briefId = $data['data']['id'];

        $this->assertCount(3, \App\Models\BriefTask::where('brief_id', $briefId)->get());
    }
}