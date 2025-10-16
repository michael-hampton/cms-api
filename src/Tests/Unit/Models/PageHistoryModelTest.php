<?php

namespace App\Tests\Unit\Models;

use App\Framework\Database\Relations\RelationBuilder;
use App\Models\Page;
use App\Models\PageHistory;
use App\Models\Site;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PageHistoryModelTest extends FunctionalTestCase
{
    protected PageHistory $pageHistory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageHistory = new PageHistory([
            'page_id' => 1,
            'user_id' => 1,
            'site_id' => 1,
            'action' => 'updated',
            'description' => 'Page content updated',
            'changes' => json_encode([
                'title' => ['old' => 'Old Title', 'new' => 'New Title'],
                'status' => ['old' => 'draft', 'new' => 'published']
            ]),
            'snapshot' => json_encode(['title' => 'New Title', 'content' => 'Content']),
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => '2025-01-15 10:00:00'
        ]);
    }

    public function testPageHistoryCanBeInstantiated()
    {
        $this->assertInstanceOf(PageHistory::class, $this->pageHistory);
    }

    public function testPageHistoryHasCorrectTableName()
    {
        $this->assertEquals('page_history', $this->pageHistory->getTable());
    }

    public function testTimestampsIsSetToFalse()
    {
        $this->assertFalse($this->pageHistory->timestamps);
    }

    public function testPageRelationReturnsCorrectType()
    {
        $relation = $this->pageHistory->page(true);
        $this->assertInstanceOf(RelationBuilder::class, $relation);
    }

    public function testUserRelationReturnsCorrectType()
    {
        $relation = $this->pageHistory->user(true);
        $this->assertInstanceOf(RelationBuilder::class, $relation);
    }

    public function testGetChangeSummaryWithChanges()
    {
        $this->pageHistory->changes = [
            'title' => ['old' => 'Old', 'new' => 'New'],
            'status' => ['old' => 'draft', 'new' => 'published'],
            'blocks_added' => 2,
            'blocks_removed' => 1,
            'blocks_modified' => 3
        ];

        $summary = $this->pageHistory->getChangeSummary();

        $this->assertStringContainsString('Title changed', $summary);
        $this->assertStringContainsString('Status: draft → published', $summary);
        $this->assertStringContainsString('2 block(s) added', $summary);
        $this->assertStringContainsString('1 block(s) removed', $summary);
        $this->assertStringContainsString('3 block(s) modified', $summary);
    }

    public function testGetChangeSummaryWithoutChanges()
    {
        $this->pageHistory->changes = null;
        $this->pageHistory->description = 'Custom description';

        $summary = $this->pageHistory->getChangeSummary();
        $this->assertEquals('Custom description', $summary);
    }

    public function testGetChangeSummaryWithEmptyChanges()
    {
        $this->pageHistory->changes = [];
        $this->pageHistory->description = 'Test description';

        $summary = $this->pageHistory->getChangeSummary();
        $this->assertEquals('Test description', $summary);
    }

    public function testGetChangeSummaryDefaultMessage()
    {
        $this->pageHistory->changes = ['test' => 'test'];
        $this->pageHistory->description = null;

        $summary = $this->pageHistory->getChangeSummary();
        $this->assertEquals('Page updated', $summary);
    }

    public function testGetUserNameReturnsUserName()
    {
        $user = new User(['name' => 'John Doe']);
        $this->pageHistory->setRelation('user', $user);

        $userName = $this->pageHistory->getUserName();
        $this->assertEquals('John Doe', $userName);
    }

    public function testGetUserNameReturnsSystemWhenNoUser()
    {
        $this->pageHistory->setRelation('user', null);

        $userName = $this->pageHistory->getUserName();
        $this->assertEquals('System', $userName);
    }

    public function testGetActionLabelReturnsCorrectLabels()
    {
        $this->pageHistory->action = 'created';
        $this->assertEquals('Created', $this->pageHistory->getActionLabel());

        $this->pageHistory->action = 'updated';
        $this->assertEquals('Updated', $this->pageHistory->getActionLabel());

        $this->pageHistory->action = 'published';
        $this->assertEquals('Published', $this->pageHistory->getActionLabel());

        $this->pageHistory->action = 'unpublished';
        $this->assertEquals('Unpublished', $this->pageHistory->getActionLabel());

        $this->pageHistory->action = 'duplicated';
        $this->assertEquals('Duplicated', $this->pageHistory->getActionLabel());

        $this->pageHistory->action = 'deleted';
        $this->assertEquals('Deleted', $this->pageHistory->getActionLabel());

        $this->pageHistory->action = 'restored';
        $this->assertEquals('Restored', $this->pageHistory->getActionLabel());
    }

    public function testGetActionLabelHandlesUnknownAction()
    {
        $this->pageHistory->action = 'custom_action';
        $this->assertEquals('Custom_action', $this->pageHistory->getActionLabel());
    }

    public function testSetAndGetPageId()
    {
        $this->pageHistory->page_id = 10;
        $this->assertEquals(10, $this->pageHistory->page_id);
    }

    public function testSetAndGetUserId()
    {
        $this->pageHistory->user_id = 5;
        $this->assertEquals(5, $this->pageHistory->user_id);
    }

    public function testSetAndGetSiteId()
    {
        $this->pageHistory->site_id = 2;
        $this->assertEquals(2, $this->pageHistory->site_id);
    }

    public function testSetAndGetAction()
    {
        $this->pageHistory->action = 'created';
        $this->assertEquals('created', $this->pageHistory->action);
    }

    public function testSetAndGetDescription()
    {
        $this->pageHistory->description = 'New description';
        $this->assertEquals('New description', $this->pageHistory->description);
    }

    public function testSetAndGetChanges()
    {
        $changes = ['field' => ['old' => 'old_value', 'new' => 'new_value']];
        $this->pageHistory->changes = $changes;
        $this->assertEquals($changes, $this->pageHistory->changes);
    }

    public function testSetAndGetSnapshot()
    {
        $snapshot = ['title' => 'Test', 'content' => 'Test content'];
        $this->pageHistory->snapshot = $snapshot;
        $this->assertEquals($snapshot, $this->pageHistory->snapshot);
    }

    public function testSetAndGetIpAddress()
    {
        $this->pageHistory->ip_address = '10.0.0.1';
        $this->assertEquals('10.0.0.1', $this->pageHistory->ip_address);
    }

    public function testSetAndGetUserAgent()
    {
        $this->pageHistory->user_agent = 'Chrome/91.0';
        $this->assertEquals('Chrome/91.0', $this->pageHistory->user_agent);
    }

    public function testCreatePageHistory()
    {
        $page = Page::create(['title' => 'test', 'site_id' => 1, 'slug' => 'test-page']);
        $user = User::create(['name' => 'John Doe', 'email' => '<EMAIL>', 'password' => '<PASSWORD>']);
        $site = Site::create(['name' => 'Test Site', 'domain' => 'test.com']);

        $pageHistory = PageHistory::create([
            'page_id' => $page->id,
            'user_id' => $user->id,
            'site_id' => $site->id,
            'action' => 'created',
            'description' => 'Page was created',
            'ip_address' => '192.168.1.100',
            'created_at' => '2025-01-15 10:00:00',
        ]);

        $this->assertInstanceOf(PageHistory::class, $pageHistory);
        $this->assertEquals($page->id, $pageHistory->page_id);
        $this->assertEquals('created', $pageHistory->action);
        $this->assertEquals('Page was created', $pageHistory->description);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $pageHistory = new PageHistory();
        $pageHistory->fill([
            'page_id' => 10,
            'action' => 'deleted',
            'description' => 'Page deleted'
        ]);

        $this->assertEquals(10, $pageHistory->page_id);
        $this->assertEquals('deleted', $pageHistory->action);
        $this->assertEquals('Page deleted', $pageHistory->description);
    }

    public function testChangesCanStoreComplexData()
    {
        $complexChanges = [
            'title' => ['old' => 'Old', 'new' => 'New'],
            'content' => ['old' => 'Old content', 'new' => 'New content'],
            'metadata' => [
                'seo_title' => ['old' => 'Old SEO', 'new' => 'New SEO']
            ]
        ];

        $this->pageHistory->changes = $complexChanges;
        $retrieved = $this->pageHistory->changes;

        $this->assertIsArray($retrieved['metadata']);
        $this->assertEquals('Old SEO', $retrieved['metadata']['seo_title']['old']);
    }

    public function testSnapshotCanStoreFullPageData()
    {
        $fullSnapshot = [
            'id' => 1,
            'title' => 'Test Page',
            'content' => 'Full content',
            'blocks' => [
                ['type' => 'text', 'content' => 'Block 1'],
                ['type' => 'image', 'url' => 'image.jpg']
            ]
        ];

        $this->pageHistory->snapshot = $fullSnapshot;
        $retrieved = $this->pageHistory->snapshot;

        $this->assertEquals('Test Page', $retrieved['title']);
        $this->assertCount(2, $retrieved['blocks']);
        $this->assertEquals('text', $retrieved['blocks'][0]['type']);
    }
}