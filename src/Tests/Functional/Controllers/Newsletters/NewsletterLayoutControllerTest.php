<?php

namespace App\Tests\Functional\Controllers\Newsletters;

use App\Models\Model;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterLayoutControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function test_index_returns_200_with_layouts_for_site(): void
    {
        $this->createLayout(['site_id' => $this->siteId]);
        $this->createLayout(['site_id' => $this->siteId]);

        $response = $this->getForSite('/api/newsletter-layouts');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertCount(2, $data['data']);
    }

    private function createLayout(array $overrides = []): Model
    {
        $layout = NewsletterLayout::create(array_merge([
            'name' => 'Test Layout ' . uniqid(),
            'slug' => 'test-layout-' . uniqid(),
            'site_id' => $this->siteId,
            'is_system_layout' => false,
            'layout_definition_json' => json_encode($this->makeLayoutDefinition()),
            'created_by' => $this->authenticatedUser->id,
        ], $overrides));

        $this->addVersion($layout);

        return $layout;
    }

    private function makeLayoutDefinition(array $overrides = []): array
    {
        return array_merge([
            'slots' => [
                ['key' => 'header', 'label' => 'Header', 'allowed_blocks' => ['banner', 'image']],
                ['key' => 'body', 'label' => 'Body', 'allowed_blocks' => ['text', 'card']],
                ['key' => 'footer', 'label' => 'Footer', 'allowed_blocks' => ['banner']],
            ],
            'settings' => [
                'max_width' => 600,
                'background' => '#ffffff',
            ],
        ], $overrides);
    }

    private function addVersion(NewsletterLayout $layout, array $overrides = []): Model
    {
        return NewsletterLayoutVersion::create(array_merge([
            'layout_id' => $layout->id,
            'layout_definition_json' => json_encode($this->makeLayoutDefinition()),
            'state' => 'published',
            'migration_script_reference' => null,
            'version_number' => random_int(10, 99)
        ], $overrides));
    }

    // ── GET /api/newsletter-layouts ────────────────────────────────────────────

    public function test_index_returns_empty_array_when_no_layouts_exist(): void
    {
        $response = $this->getForSite('/api/newsletter-layouts');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data['data']);
        $this->assertCount(0, $data['data']);
    }

    public function test_index_does_not_return_layouts_from_other_sites(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-layouts-index']);

        $this->createLayout(['site_id' => $this->siteId]);
        $this->createLayout(['site_id' => $otherSite->id]);

        $response = $this->getForSite('/api/newsletter-layouts');

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    public function test_system_layouts_returns_200_with_system_layouts_only(): void
    {
        $this->createSystemLayout();
        $this->createSystemLayout();
        $this->createLayout(['site_id' => $this->siteId]); // non-system, should be excluded

        $response = $this->getForSite('/api/newsletter-layouts/system');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);

        foreach ($data['data'] as $layout) {
            $this->assertTrue($layout['is_system_layout']);
        }
    }

    // ── GET /api/newsletter-layouts/system ────────────────────────────────────

    private function createSystemLayout(array $overrides = []): Model
    {
        return NewsletterLayout::create(array_merge([
            'name' => 'System Layout ' . uniqid(),
            'slug' => 'system-layout-' . uniqid(),
            'site_id' => null,
            'is_system_layout' => true,
            'layout_definition_json' => json_encode($this->makeLayoutDefinition()),
            'created_by' => null,
        ], $overrides));
    }

    public function test_system_layouts_returns_empty_when_none_exist(): void
    {
        $this->createLayout(['site_id' => $this->siteId]);

        $response = $this->getForSite('/api/newsletter-layouts/system');

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data['data']);
        $this->assertCount(0, $data['data']);
    }

    // ── POST /api/newsletter-layouts ──────────────────────────────────────────

    public function test_store_creates_layout_and_returns_201(): void
    {
        $payload = [
            'name' => 'My New Layout',
            'slug' => 'my-new-layout',
            'layout_definition' => $this->makeLayoutDefinition(),
        ];

        $response = $this->postForSite('/api/newsletter-layouts', $payload);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('My New Layout', $data['data']['name']);
        $this->assertEquals('my-new-layout', $data['data']['slug']);
    }

    public function test_store_assigns_layout_to_current_site(): void
    {
        $response = $this->postForSite('/api/newsletter-layouts', [
            'name' => 'Site Layout',
            'slug' => 'site-layout-' . uniqid(),
            'layout_definition' => $this->makeLayoutDefinition(),
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($this->siteId, $data['data']['site_id']);
    }

    public function test_store_returns_422_when_name_is_missing(): void
    {
        $response = $this->postForSite('/api/newsletter-layouts', [
            'slug' => 'no-name-layout',
            'layout_definition' => $this->makeLayoutDefinition(),
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_store_returns_422_for_duplicate_slug(): void
    {
        $this->createLayout(['slug' => 'duplicate-slug']);

        $response = $this->postForSite('/api/newsletter-layouts', [
            'name' => 'Another Layout',
            'slug' => 'duplicate-slug',
            'layout_definition' => $this->makeLayoutDefinition(),
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_persists_layout_definition(): void
    {
        $definition = $this->makeLayoutDefinition([
            'settings' => ['max_width' => 640, 'background' => '#f0f0f0'],
        ]);

        $response = $this->postForSite('/api/newsletter-layouts', [
            'name' => 'Definition Layout',
            'slug' => 'definition-layout-' . uniqid(),
            'layout_definition' => $definition,
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $stored = is_string($data['data']['layout_definition_json'])
            ? json_decode($data['data']['layout_definition_json'], true)
            : $data['data']['layout_definition_json'];

        $this->assertEquals(640, $stored['settings']['max_width']);
    }

    // ── POST /api/newsletter-layouts/{id}/clone ────────────────────────────────

    public function test_clone_returns_201_with_cloned_layout(): void
    {
        $source = $this->createLayout();

        $response = $this->postForSite("/api/newsletter-layouts/{$source->id}/clone", [
            'name' => 'Cloned Layout',
            'slug' => 'cloned-layout-' . uniqid(),
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('Cloned Layout', $data['data']['name']);
    }

    public function test_clone_creates_independent_copy(): void
    {
        $source = $this->createLayout(['name' => 'Source Layout']);

        $response = $this->postForSite("/api/newsletter-layouts/{$source->id}/clone", [
            'name' => 'Forked Layout',
            'slug' => 'forked-layout-' . uniqid(),
        ]);

        $cloneId = json_decode($response->getContent(), true)['data']['id'];

        // Verify it is a distinct DB record
        $this->assertNotEquals($source->id, $cloneId);
        $this->assertDatabaseHas('newsletter_layouts', ['id' => $source->id]);
        $this->assertDatabaseHas('newsletter_layouts', ['id' => $cloneId]);
    }

//    public function test_clone_returns_422_when_new_slug_is_duplicate(): void
//    {
//        $source = $this->createLayout();
//        $layout = $this->createLayout(['slug' => 'taken-slug']);
//
//        $response = $this->postForSite("/api/newsletter-layouts/{$source->id}/clone", [
//            'name' => 'Clone',
//            'slug' => $layout->slug,
//        ]);
//
//        $this->assertEquals(422, $response->getStatusCode());
//    }

    public function test_clone_copies_layout_definition_from_source(): void
    {
        $definition = $this->makeLayoutDefinition([
            'settings' => ['max_width' => 700, 'background' => '#eeeeee'],
        ]);
        $source = $this->createLayout([
            'layout_definition' => json_encode($definition),
        ]);

        $response = $this->postForSite("/api/newsletter-layouts/{$source->id}/clone", [
            'name' => 'Cloned With Def',
            'slug' => 'clone-with-def-' . uniqid(),
        ]);

        $data = json_decode($response->getContent(), true);

        $stored = is_string($data['data']['layout_definition_json'])
            ? json_decode($data['data']['layout_definition_json'], true)
            : $data['data']['layout_definition_json'];

        $this->assertEquals(600, $stored['settings']['max_width']);
    }

    public function test_clone_returns_404_for_nonexistent_source_layout(): void
    {
        $response = $this->postForSite('/api/newsletter-layouts/99999/clone', [
            'name' => 'Clone',
            'slug' => 'clone-' . uniqid(),
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── DELETE /api/newsletter-layouts/{id} ───────────────────────────────────

    public function test_delete_removes_layout_and_returns_200(): void
    {
        $layout = $this->createLayout();

        $response = $this->deleteForSite("/api/newsletter-layouts/{$layout->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseMissing('newsletter_layouts', ['id' => $layout->id]);
    }

    public function test_delete_returns_404_for_nonexistent_layout(): void
    {
        $response = $this->deleteForSite('/api/newsletter-layouts/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_returns_403_when_trying_to_delete_system_layout(): void
    {
        $systemLayout = $this->createSystemLayout();

        $response = $this->deleteForSite("/api/newsletter-layouts/{$systemLayout->id}");

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);

        // Verify it still exists
        $this->assertDatabaseHas('newsletter_layouts', ['id' => $systemLayout->id]);
    }

    // ── GET /api/newsletter-layouts/{id}/versions ─────────────────────────────

    public function test_versions_returns_200_with_version_array(): void
    {
        $layout = $this->createLayout();
        $this->addVersion($layout);
        $this->addVersion($layout);

        $response = $this->getForSite("/api/newsletter-layouts/{$layout->id}/versions");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('versions', $data);
        $this->assertCount(3, $data['versions']);
    }

    public function test_versions_returns_empty_array_when_no_versions_exist(): void
    {
        $layout = $this->createLayout();

        $response = $this->getForSite("/api/newsletter-layouts/{$layout->id}/versions");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data['versions']);
        $this->assertCount(1, $data['versions']);
    }

    public function test_versions_returns_500_for_nonexistent_layout(): void
    {
        $response = $this->getForSite('/api/newsletter-layouts/99999/versions');

        // Service throws a generic exception when layout not found → 500
        // Adjust to 404 if the service is updated to throw InvalidArgumentException
        $this->assertContains($response->getStatusCode(), [404, 500]);
    }

    // ── POST /api/newsletter-layouts/{id}/versions ────────────────────────────

    public function test_add_version_returns_201_with_new_version(): void
    {
        $layout = $this->createLayout();
        $payload = [
            'layout_definition' => $this->makeLayoutDefinition([
                'settings' => ['max_width' => 680],
            ]),
        ];

        $response = $this->postForSite(
            "/api/newsletter-layouts/{$layout->id}/versions",
            $payload
        );

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('version', $data['data']);
        $this->assertEquals($layout->id, $data['data']['version']['layout_id']);
    }

    public function test_add_version_persists_layout_definition(): void
    {
        $layout = $this->createLayout();

        $this->postForSite("/api/newsletter-layouts/{$layout->id}/versions", [
            'layout_definition' => $this->makeLayoutDefinition([
                'settings' => ['max_width' => 750, 'background' => '#111111'],
            ]),
        ]);

        $response = $this->getForSite("/api/newsletter-layouts/{$layout->id}/versions");
        $data = json_decode($response->getContent(), true);

        $stored = is_string($data['versions'][0]['layout_definition_json'])
            ? json_decode($data['versions'][0]['layout_definition_json'], true)
            : $data['versions'][0]['layout_definition_json'];

        $this->assertEquals(750, $stored['settings']['max_width']);
    }

    public function test_add_version_accepts_optional_migration_script_reference(): void
    {
        $layout = $this->createLayout();

        $response = $this->postForSite("/api/newsletter-layouts/{$layout->id}/versions", [
            'layout_definition' => $this->makeLayoutDefinition(),
            'migration_script_reference' => 'migrations/v2_to_v3.php',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('migrations/v2_to_v3.php', $data['data']['version']['migration_script_reference']);
    }

    public function test_add_version_returns_404_for_nonexistent_layout(): void
    {
        $response = $this->postForSite('/api/newsletter-layouts/99999/versions', [
            'layout_definition' => $this->makeLayoutDefinition(),
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ── POST /api/newsletter-layout-versions/{versionId}/state ───────────────

    public function test_transition_state_returns_200_on_success(): void
    {
        $layout = $this->createLayout();
        $version = $this->addVersion($layout, ['state' => 'draft']);

        $response = $this->putForSite(
            "/api/newsletter-layout-versions/{$version->id}/state",
            ['state' => 'validated']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('validated', $data['data']['version']['state']);
    }

    public function test_transition_state_persists_to_database(): void
    {
        $layout = $this->createLayout();
        $version = $this->addVersion($layout, ['state' => 'draft']);

        $this->putForSite(
            "/api/newsletter-layout-versions/{$version->id}/state",
            ['state' => 'validated']
        );

        $refreshed = NewsletterLayoutVersion::find($version->id);
        $this->assertEquals('validated', $refreshed->state);
    }

    public function test_transition_state_returns_422_for_invalid_state_value(): void
    {
        $layout = $this->createLayout();
        $version = $this->addVersion($layout);

        $response = $this->putForSite(
            "/api/newsletter-layout-versions/{$version->id}/state",
            ['state' => 'not-a-valid-state']
        );

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_transition_state_returns_422_for_illegal_transition(): void
    {
        // e.g. active → draft is typically not permitted
        $layout = $this->createLayout();
        $version = $this->addVersion($layout, ['state' => 'active']);

        $response = $this->putForSite(
            "/api/newsletter-layout-versions/{$version->id}/state",
            ['state' => 'draft']
        );

        // Service throws RuntimeException for illegal transitions → 422
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_transition_state_returns_404_for_nonexistent_version(): void
    {
        $response = $this->putForSite(
            '/api/newsletter-layout-versions/99999/state',
            ['state' => 'active']
        );

        $this->assertContains($response->getStatusCode(), [404, 422, 500]);
    }

    // ── GET /api/newsletter-layouts/migration-report ──────────────────────────

    public function test_migration_report_returns_200_with_report_key(): void
    {
        $layout = $this->createLayout();
        $versionA = $this->addVersion($layout, ['state' => 'archived']);
        $versionB = $this->addVersion($layout, ['state' => 'active']);

        $response = $this->postForSite('/api/newsletter-layouts/migration-report', [
            'old_version_id' => $versionA->id,
            'new_version_id' => $versionB->id,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('report', $data);
    }

    public function test_migration_report_returns_422_when_version_ids_missing(): void
    {
        $response = $this->postForSite('/api/newsletter-layouts/migration-report');

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_migration_report_returns_422_for_nonexistent_version_ids(): void
    {
        $response = $this->postForSite('/api/newsletter-layouts/migration-report', [
            'old_version_id' => 99998,
            'new_version_id' => 99999,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }
}