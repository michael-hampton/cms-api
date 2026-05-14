<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;

/**
 * Functional tests for AdminGuidelineTemplateController.
 *
 * Covers: index, store, update, destroy.
 * All authenticated requests run as admin (FunctionalTestCase::actingAs default).
 *
 * Routes (from web.php, inside RequireAdminRole group):
 *   GET    /api/{site}/open-collab/admin/guideline-templates
 *   POST   /api/{site}/open-collab/admin/guideline-templates
 *   PUT    /api/{site}/open-collab/admin/guideline-templates/{id}
 *   DELETE /api/{site}/open-collab/admin/guideline-templates/{id}
 */
class AdminGuidelineTemplateControllerTest extends FunctionalTestCase
{
    public function test_index_returns_empty_array_when_no_templates_exist(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/guideline-templates');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertIsArray($body['data']);
        $this->assertEmpty($body['data']);
    }

    // ── index ─────────────────────────────────────────────────────────────

    public function test_index_returns_all_active_templates(): void
    {
        $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Editorial Standards',
            'slug' => 'editorial-standards',
            'content' => 'Follow the AP style guide.',
        ]);
        $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Community Rules',
            'slug' => 'community-rules',
            'content' => 'Be respectful.',
        ]);

        $response = $this->getForSite('/api/open-collab/admin/guideline-templates');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertCount(2, $body['data']);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/admin/guideline-templates');

        $this->assertResponseStatus(401, $response);
    }

    public function test_store_creates_template_and_returns_201(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Writing Guidelines',
            'slug' => 'writing-guidelines',
            'content' => 'Keep it concise.',
            'description' => 'General writing expectations.',
        ]);

        $this->assertResponseStatus(201, $response);

        $body = $this->decodeJson($response);
        $this->assertEquals('Writing Guidelines', $body['name']);
        $this->assertEquals('writing-guidelines', $body['slug']);

        $this->assertDatabaseHas('oc_guideline_templates', [
            'name' => 'Writing Guidelines',
            'slug' => 'writing-guidelines',
        ]);
    }

    // ── store ─────────────────────────────────────────────────────────────

    public function test_store_persists_optional_description(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Image Policy',
            'slug' => 'image-policy',
            'content' => 'Use royalty-free images only.',
            'description' => 'Rules for image usage.',
        ]);

        $this->assertResponseStatus(201, $response);

        $this->assertDatabaseHas('oc_guideline_templates', [
            'slug' => 'image-policy',
            'description' => 'Rules for image usage.',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/admin/guideline-templates', [
            'name' => 'Ghost Guideline',
            'slug' => 'ghost-guideline',
            'content' => 'Should not be stored.',
        ]);

        $this->assertResponseStatus(401, $response);

        $this->assertDatabaseMissing('oc_guideline_templates', ['slug' => 'ghost-guideline']);
    }

    public function test_update_modifies_existing_template(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Old Guideline',
            'slug' => 'old-guideline',
            'content' => 'Old content.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->putForSite(
            '/api/open-collab/admin/guideline-templates/' . $created['id'],
            [
                'name' => 'Revised Guideline',
                'content' => 'Revised content.',
                'description' => 'Added description.',
            ]
        );

        $this->assertResponseStatus(200, $response);

        $body = $this->decodeJson($response);
        $this->assertEquals('Revised Guideline', $body['name']);
        $this->assertEquals('Revised content.', $body['content']);
    }

    // ── update ────────────────────────────────────────────────────────────

    public function test_update_returns_404_for_missing_template(): void
    {
        $response = $this->putForSite('/api/open-collab/admin/guideline-templates/99999', [
            'name' => 'Ghost',
            'content' => 'Ghost content.',
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_update_requires_authentication(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Auth Guard Test',
            'slug' => 'auth-guard-test',
            'content' => 'Protected.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->putForSiteUnauthenticated(
            '/api/open-collab/admin/guideline-templates/' . $created['id'],
            ['name' => 'Hijacked', 'content' => 'Hijacked.']
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_destroy_deactivates_template(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'To Deactivate',
            'slug' => 'to-deactivate',
            'content' => 'Soon gone.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->deleteForSite(
            '/api/open-collab/admin/guideline-templates/' . $created['id']
        );

        $this->assertResponseStatus(200, $response);

        $this->assertDatabaseHas('oc_guideline_templates', [
            'id' => $created['id'],
            'is_active' => 0,
        ]);
    }

    // ── destroy ───────────────────────────────────────────────────────────

    public function test_destroy_returns_404_for_missing_template(): void
    {
        $response = $this->deleteForSite('/api/open-collab/admin/guideline-templates/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_destroy_requires_authentication(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/guideline-templates', [
            'name' => 'Still Active',
            'slug' => 'still-active',
            'content' => 'Should remain active.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->deleteForSiteUnauthenticated(
            '/api/open-collab/admin/guideline-templates/' . $created['id']
        );

        $this->assertResponseStatus(401, $response);

        $this->assertDatabaseHas('oc_guideline_templates', [
            'id' => $created['id'],
            'is_active' => 1,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}