<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;

/**
 * Functional tests for AdminContractTemplateController.
 *
 * Covers: index, store, update, destroy.
 * All requests are made as an admin user (set up by FunctionalTestCase::actingAs).
 *
 * Routes (from web.php, inside RequireAdminRole group):
 *   GET    /api/{site}/open-collab/admin/contract-templates
 *   POST   /api/{site}/open-collab/admin/contract-templates
 *   PUT    /api/{site}/open-collab/admin/contract-templates/{id}
 *   DELETE /api/{site}/open-collab/admin/contract-templates/{id}
 */
class AdminContractTemplateControllerTest extends FunctionalTestCase
{
    // ── index ─────────────────────────────────────────────────────────────

    public function test_index_returns_empty_array_when_no_templates_exist(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/contract-templates');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertIsArray($body['data']);
        $this->assertEmpty($body['data']);
    }

    public function test_index_returns_active_templates(): void
    {
        $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'Standard Contract',
            'slug' => 'standard-contract',
            'content' => 'Contract body here.',
        ]);

        $response = $this->getForSite('/api/open-collab/admin/contract-templates');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertCount(1, $body['data']);
        $this->assertEquals('Standard Contract', $body['data'][0]['name']);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/admin/contract-templates');

        $this->assertResponseStatus(401, $response);
    }

    // ── store ─────────────────────────────────────────────────────────────

    public function test_store_creates_template_and_returns_201(): void
    {
        $unique = uniqid();
        $response = $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'Freelance Agreement',
            'slug' => 'freelance-agreement-' . $unique,
            'content' => 'Full contract text.',
            'description' => 'For freelance contributors.',
        ]);

        $this->assertResponseStatus(201, $response);

        $body = $this->decodeJson($response);
        $this->assertEquals('Freelance Agreement', $body['name']);
        $this->assertEquals('freelance-agreement-' . $unique, $body['slug']);

        $this->assertDatabaseHas('oc_contract_templates', [
            'name' => 'Freelance Agreement',
            'slug' => 'freelance-agreement-' . $unique,
        ]);
    }

    public function test_store_persists_optional_description(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'NDA Template',
            'slug' => 'nda-template',
            'content' => 'NDA content.',
            'description' => 'Non-disclosure agreement.',
        ]);

        $this->assertResponseStatus(201, $response);

        $this->assertDatabaseHas('oc_contract_templates', [
            'slug' => 'nda-template',
            'description' => 'Non-disclosure agreement.',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/admin/contract-templates', [
            'name' => 'Unauthorised',
            'slug' => 'unauthorised',
            'content' => 'Should not be created.',
        ]);

        $this->assertResponseStatus(401, $response);

        $this->assertDatabaseMissing('oc_contract_templates', ['slug' => 'unauthorised']);
    }

    // ── update ────────────────────────────────────────────────────────────

    public function test_update_modifies_existing_template(): void
    {
        $unique = uniqid();
        $create = $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'Original Name',
            'slug' => 'original-name-' . $unique,
            'content' => 'Original content.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->putForSite(
            '/api/open-collab/admin/contract-templates/' . $created['id'],
            [
                'name' => 'Updated Name',
                'content' => 'Updated content.',
                'description' => 'Now with a description.',
            ]
        );

        $this->assertResponseStatus(200, $response);

        $body = $this->decodeJson($response);
        $this->assertEquals('Updated Name', $body['name']);
        $this->assertEquals('Updated content.', $body['content']);
    }

    public function test_update_returns_404_for_missing_template(): void
    {
        $response = $this->putForSite('/api/open-collab/admin/contract-templates/99999', [
            'name' => 'Ghost',
            'content' => 'Ghost content.',
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_update_requires_authentication(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'Auth Test',
            'slug' => 'auth-test',
            'content' => 'Auth content.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->putForSiteUnauthenticated(
            '/api/open-collab/admin/contract-templates/' . $created['id'],
            ['name' => 'Hijacked', 'content' => 'Hijacked content.']
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── destroy ───────────────────────────────────────────────────────────

    public function test_destroy_deactivates_template(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'content' => 'Delete me.',
        ]);
        $created = $this->decodeJson($create);

        $response = $this->deleteForSite(
            '/api/open-collab/admin/contract-templates/' . $created['id']
        );

        $this->assertResponseStatus(200, $response);

        // The record should still exist but be inactive (soft-deactivated).
        $this->assertDatabaseHas('oc_contract_templates', [
            'id' => $created['id'],
            'is_active' => 0,
        ]);
    }

    public function test_destroy_returns_404_for_missing_template(): void
    {
        $response = $this->deleteForSite('/api/open-collab/admin/contract-templates/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_destroy_requires_authentication(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/contract-templates', [
            'name' => 'Protected',
            'slug' => 'protected-' . uniqid(),
            'content' => 'Do not delete.',
        ]);
        $created = $this->decodeJson($create);

        $this->unauthenticate();

        $response = $this->deleteForSiteUnauthenticated(
            '/api/open-collab/admin/contract-templates/' . $created['id']
        );

        $this->assertResponseStatus(401, $response);

        // Template must still be active.
        $this->assertDatabaseHas('oc_contract_templates', [
            'id' => $created['id'],
            'is_active' => 1,
        ]);
    }
}