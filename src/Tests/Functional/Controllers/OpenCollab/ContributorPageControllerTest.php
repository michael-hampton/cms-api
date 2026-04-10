<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\Page;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ContributorPageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $otherContributor;

    public function test_contributor_sees_only_their_own_pages(): void
    {
        // Own page
        $this->createPage(['contributor_id' => $this->contributor->id, 'title' => 'Mine']);
        // Another contributor's page — must not appear
        $this->createPage(['contributor_id' => $this->otherContributor->id, 'title' => 'Not Mine']);

        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/pages');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $pages = $data['data'];

        $this->assertCount(1, $pages);
        $this->assertEquals('Mine', $pages[0]['title']);
    }

    // -------------------------------------------------------------------------
    // GET /api/{site}/open-collab/pages
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_list_pages(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/pages');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_contributor_can_create_page(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->postForSite('/api/open-collab/pages', [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'My First Article'],
                'meta' => ['status' => 'draft'],
            ],
            'blocks' => [],
            'is_paid' => false
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('My First Article', $data['data']['page']['title']);

        $this->assertDatabaseHas('pages', [
            'title' => 'My First Article',
            'contributor_id' => $this->contributor->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/{site}/open-collab/pages
    // -------------------------------------------------------------------------

    public function test_create_page_sets_is_public_contribution_automatically(): void
    {
        $this->actingAs($this->contributor);

        $this->postForSite('/api/open-collab/pages', [
            'site_id' => $this->siteId,
            'forms' => ['main' => ['title' => 'Public Article']],
        ]);

        $this->assertDatabaseHas('pages', [
            'title' => 'Public Article',
            'is_public_contribution' => 1,
        ]);
    }

    public function test_create_page_requires_title(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->postForSite('/api/open-collab/pages', [
            'site_id' => $this->siteId,
            'forms' => ['main' => []],
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_contributor_can_update_their_own_page(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'title' => 'Original Title',
            'contributor_id' => $this->contributor->id,
        ]);

        $response = $this->putForSite("/api/open-collab/pages/{$page->id}", [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Updated Title'],
                'meta' => ['status' => 'draft'],
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Title', $data['data']['page']['title']);
    }

    // -------------------------------------------------------------------------
    // PUT /api/{site}/open-collab/pages/{id}
    // -------------------------------------------------------------------------

    public function test_contributor_cannot_update_another_contributors_page(): void
    {
        $this->actingAs($this->contributor);

        $otherPage = $this->createPage([
            'title' => 'Someone Elses Article',
            'contributor_id' => $this->otherContributor->id,
        ]);

        $response = $this->putForSite("/api/open-collab/pages/{$otherPage->id}", [
            'site_id' => $this->siteId,
            'forms' => ['main' => ['title' => 'Hijacked']],
        ]);

        $this->assertEquals(403, $response->getStatusCode());

        $this->assertDatabaseMissing('pages', ['title' => 'Hijacked']);
    }

    public function test_contributor_can_delete_their_own_page(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);

        $response = $this->deleteForSite("/api/open-collab/pages/{$page->id}");

        $this->assertEquals(204, $response->getStatusCode());

        $page = Page::find($page->id);
        $this->assertEmpty($page);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/{site}/open-collab/pages/{id}
    // -------------------------------------------------------------------------

    public function test_contributor_cannot_delete_another_contributors_page(): void
    {
        $this->actingAs($this->contributor);

        $otherPage = $this->createPage(['contributor_id' => $this->otherContributor->id]);

        $response = $this->deleteForSite("/api/open-collab/pages/{$otherPage->id}");

        $this->assertEquals(403, $response->getStatusCode());

        $this->assertDatabaseHas('pages', ['id' => $otherPage->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->otherContributor = $this->createUser([
            'email' => 'other@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }
}