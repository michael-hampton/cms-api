<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;

class AdminTermsControllerTest extends FunctionalTestCase
{
    public function test_index_returns_empty_terms_collection(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/terms');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertSame([], $body['terms']);
    }

    public function test_store_creates_draft_terms_version(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/terms', [
            'semantic_version' => '1.0.0',
            'title' => 'Contributor Terms',
            'source_content' => str_repeat('These are the Open Collab contributor terms. ', 3),
            'source_format' => 'html',
            'is_material_change' => true,
            'change_summary' => 'Initial published terms.',
        ]);

        $this->assertResponseStatus(201, $response);
        $body = $this->decodeJson($response);
        $this->assertSame('1.0.0', $body['terms']['semantic_version']);
        $this->assertSame('draft', $body['terms']['status']);
        $this->assertDatabaseHas('oc_terms_versions', [
            'semantic_version' => '1.0.0',
            'title' => 'Contributor Terms',
            'status' => 'draft',
            'is_material_change' => 1,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/terms', []);

        $this->assertResponseStatus(422, $response);
        $this->assertDatabaseMissing('oc_terms_versions', ['semantic_version' => '1.0.0']);
    }

    public function test_show_returns_site_scoped_terms_version(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/terms', [
            'semantic_version' => '1.0.1',
            'title' => 'Contributor Terms',
            'source_content' => str_repeat('Terms content for a single site. ', 3),
        ]);
        $created = $this->decodeJson($create);

        $response = $this->getForSite('/api/open-collab/admin/terms/' . $created['terms']['id']);

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertSame($created['terms']['id'], $body['terms']['id']);
    }

    public function test_update_changes_draft_content(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/terms', [
            'semantic_version' => '1.0.2',
            'title' => 'Contributor Terms',
            'source_content' => str_repeat('Original terms wording. ', 4),
        ]);
        $created = $this->decodeJson($create);

        $response = $this->putForSite('/api/open-collab/admin/terms/' . $created['terms']['id'], [
            'title' => 'Updated Contributor Terms',
            'source_content' => str_repeat('Updated terms wording. ', 4),
        ]);

        $this->assertResponseStatus(200, $response);
        $this->assertDatabaseHas('oc_terms_versions', [
            'id' => $created['terms']['id'],
            'title' => 'Updated Contributor Terms',
        ]);
    }

    public function test_publish_stores_rendered_snapshot_and_hash(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/terms', [
            'semantic_version' => '2.0.0',
            'title' => 'Material Contributor Terms',
            'source_content' => str_repeat('<p>Material terms wording.</p>', 3),
            'source_format' => 'html',
            'is_material_change' => true,
        ]);
        $created = $this->decodeJson($create);

        $response = $this->postForSite('/api/open-collab/admin/terms/' . $created['terms']['id'] . '/publish');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertSame('published', $body['terms']['status']);
        $this->assertSame(
            hash('sha256', $body['terms']['rendered_content']),
            $body['terms']['rendered_hash']
        );
    }

    public function test_published_terms_cannot_be_edited(): void
    {
        $create = $this->postForSite('/api/open-collab/admin/terms', [
            'semantic_version' => '2.0.1',
            'title' => 'Locked Terms',
            'source_content' => str_repeat('<p>Locked terms wording.</p>', 3),
        ]);
        $created = $this->decodeJson($create);
        $this->postForSite('/api/open-collab/admin/terms/' . $created['terms']['id'] . '/publish');

        $response = $this->putForSite('/api/open-collab/admin/terms/' . $created['terms']['id'], [
            'title' => 'Illegally Changed',
        ]);

        $this->assertResponseStatus(409, $response);
        $this->assertDatabaseMissing('oc_terms_versions', [
            'id' => $created['terms']['id'],
            'title' => 'Illegally Changed',
        ]);
    }

    public function test_routes_require_authentication(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/admin/terms');

        $this->assertResponseStatus(401, $response);
    }
}
