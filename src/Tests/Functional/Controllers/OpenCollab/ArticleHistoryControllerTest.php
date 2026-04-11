<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ArticleHistoryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $otherContributor;

    public function test_index_returns_history_for_owned_page(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $this->createPageHistory($page->id, $this->contributor->id, [
            'action' => 'created',
            'snapshot' => ['title' => 'First version'],
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $this->createPageHistory($page->id, $this->contributor->id, [
            'action' => 'updated',
            'snapshot' => ['title' => 'Second version'],
            'created_at' => '2024-06-01 00:00:00',
        ]);

        $response = $this->getForSite("/api/open-collab/pages/{$page->id}/history?limit=10");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['data']['history']);
        $this->assertEquals('updated', $data['data']['history'][0]['action']);
    }

    public function test_index_returns_404_for_page_not_owned_by_contributor(): void
    {
        $this->actingAs($this->otherContributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);

        $response = $this->getForSite("/api/open-collab/pages/{$page->id}/history");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_restore_updates_page_and_creates_restored_history_entry(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
            'title' => 'Current title',
            'content' => 'Current content',
            'slug' => 'current-slug',
        ]);

        $history = $this->createPageHistory($page->id, $this->contributor->id, [
            'action' => 'updated',
            'snapshot' => [
                'title' => 'Restored title',
                'content' => 'Restored content',
                'slug' => 'restored-slug',
            ],
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/history/{$history->id}/restore");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Restored title', $data['data']['page']['title']);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'Restored title',
            'slug' => 'restored-slug',
        ]);
        $this->assertDatabaseHas('page_history', [
            'page_id' => $page->id,
            'user_id' => $this->contributor->id,
            'action' => 'restored',
        ]);
    }

    public function test_restore_returns_404_when_history_entry_does_not_belong_to_page(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $otherPage = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $history = $this->createPageHistory($otherPage->id, $this->contributor->id, [
            'snapshot' => ['title' => 'Wrong page'],
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/history/{$history->id}/restore");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_restore_returns_422_when_snapshot_is_empty(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $history = $this->createPageHistory($page->id, $this->contributor->id, [
            'snapshot' => [],
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/history/{$history->id}/restore");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('No snapshot available for this version.', $data['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'article-history-owner@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->otherContributor = $this->createUser([
            'email' => 'article-history-other@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }
}
