<?php

namespace App\Tests\Functional\Controllers\Cms\Pages;

use App\Models\Page;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PipelineControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsPipelineStages(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'title' => 'Draft Page']);
        $this->createPage(['status' => 'waiting_approval', 'title' => 'Review Page']);
        $this->createPage(['status' => 'scheduled', 'title' => 'Scheduled Page']);
        $this->createPage(['status' => 'published', 'title' => 'Published Page']);

        // Act
        $response = $this->getForSite('/api/pipeline');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('stages', $data['data']);
        $this->assertArrayHasKey('draft', $data['data']['stages']);
        $this->assertArrayHasKey('waiting_approval', $data['data']['stages']);
        $this->assertArrayHasKey('scheduled', $data['data']['stages']);
        $this->assertArrayHasKey('published', $data['data']['stages']);
    }

    public function testIndexGroupsPagesByStatus(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'title' => 'Draft 1']);
        $this->createPage(['status' => 'draft', 'title' => 'Draft 2']);
        $this->createPage(['status' => 'waiting_approval', 'title' => 'Review 1']);

        // Act
        $response = $this->getForSite('/api/pipeline');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['stages']['draft']['cards']);
        $this->assertCount(1, $data['data']['stages']['waiting_approval']['cards']);
        $this->assertEquals(2, $data['data']['stages']['draft']['total']);
        $this->assertEquals(1, $data['data']['stages']['waiting_approval']['total']);
    }

    public function testIndexIncludesStageLimits(): void
    {
        // Act
        $response = $this->getForSite('/api/pipeline');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(10, $data['data']['stages']['draft']['limit']);
        $this->assertEquals(5, $data['data']['stages']['waiting_approval']['limit']);
        $this->assertNull($data['data']['stages']['scheduled']['limit']);
        $this->assertNull($data['data']['stages']['published']['limit']);
    }

    public function testIndexFiltersByAuthor(): void
    {
        // Arrange
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);

        $page1 = $this->createPage(['status' => 'draft', 'title' => 'Page 1']);
        $page2 = $this->createPage(['status' => 'draft', 'title' => 'Page 2']);

        $this->attachAuthorToPage($page1, $author1);
        $this->attachAuthorToPage($page2, $author2);

        // Act
        $response = $this->getForSite("/api/pipeline?author={$author1->id}");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['stages']['draft']['cards']);
        $draftPageIds = array_column($data['data']['stages']['draft']['cards'], 'id');
        $this->assertContains($page1->id, $draftPageIds);
        $this->assertNotContains($page2->id, $draftPageIds);
    }

    public function testIndexFiltersBySearchQuery(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'title' => 'Angular Tutorial']);
        $this->createPage(['status' => 'draft', 'title' => 'React Guide']);

        // Act
        $response = $this->getForSite('/api/pipeline?q=Angular');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['stages']['draft']['cards']);
        $this->assertStringContainsString('Angular', $data['data']['stages']['draft']['cards'][0]['title']);
    }

    public function testIndexFiltersByPageType(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'page_type' => 'blog', 'title' => 'Blog Post']);
        $this->createPage(['status' => 'draft', 'page_type' => 'article', 'title' => 'Article']);

        // Act
        $response = $this->getForSite('/api/pipeline?page_type=blog');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['stages']['draft']['cards']);
        $this->assertEquals('blog', $data['data']['stages']['draft']['cards'][0]['page_type']);
    }

    public function testIndexLoadsRelationships(): void
    {
        // Arrange
        $author = $this->createAuthor(['name' => 'Test Author']);
        $tag = $this->createTag(['name' => 'Test Tag']);

        $page = $this->createPage(['status' => 'draft']);
        $this->attachAuthorToPage($page, $author);
        $this->attachTagToPage($page, $tag);
        $this->createPageMetadata($page->id);

        // Act
        $response = $this->getForSite('/api/pipeline');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $draftCard = $data['data']['stages']['draft']['cards'][0];
        $this->assertNotEmpty($draftCard['pageAuthors']);
        $this->assertNotEmpty($draftCard['tags']);
        $this->assertNotEmpty($draftCard['metadata']);
    }

    public function testIndexCombinesMultipleFilters(): void
    {
        // Arrange
        $author = $this->createAuthor(['name' => 'Test Author']);

        $matchingPage = $this->createPage([
            'status' => 'draft',
            'page_type' => 'blog',
            'title' => 'Angular Tutorial'
        ]);

        $nonMatchingPage1 = $this->createPage([
            'status' => 'draft',
            'page_type' => 'article',
            'title' => 'Angular Guide'
        ]);

        $nonMatchingPage2 = $this->createPage([
            'status' => 'draft',
            'page_type' => 'blog',
            'title' => 'React Tutorial'
        ]);

        $this->attachAuthorToPage($matchingPage, $author);
        $this->attachAuthorToPage($nonMatchingPage1, $author);

        // Act
        $response = $this->getForSite("/api/pipeline?page_type=blog&author={$author->id}&q=Angular");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['stages']['draft']['cards']);
        $draftPageIds = array_column($data['data']['stages']['draft']['cards'], 'id');
        $this->assertContains($matchingPage->id, $draftPageIds);
    }

    public function testIndexReturnsEmptyStagesWhenNoPages(): void
    {
        // Act
        $response = $this->getForSite('/api/pipeline');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(0, $data['data']['stages']['draft']['cards']);
        $this->assertCount(0, $data['data']['stages']['waiting_approval']['cards']);
        $this->assertCount(0, $data['data']['stages']['scheduled']['cards']);
        $this->assertCount(0, $data['data']['stages']['published']['cards']);
    }

    public function testUpdateStageChangesPageStatus(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft']);

        // Act
        $response = $this->putForSite("/api/pipeline/{$page->id}/stage", [
            'status' => 'waiting_approval'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);
        $this->assertEquals('Page status updated successfully', $data['data']['message']);

        // Verify database
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => 'waiting_approval'
        ]);
    }

    public function testUpdateStageToScheduledSetsScheduledAt(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft', 'scheduled_at' => null]);

        // Act
        $response = $this->putForSite("/api/pipeline/{$page->id}/stage", [
            'status' => 'scheduled'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $updatedPage = Page::find($page->id);
        $this->assertEquals('scheduled', $updatedPage->status);
        $this->assertNotNull($updatedPage->scheduled_at);
    }

    public function testUpdateStageToPublishedSetsPublishedAt(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft', 'published_at' => null]);

        // Act
        $response = $this->putForSite("/api/pipeline/{$page->id}/stage", [
            'status' => 'published'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $updatedPage = Page::find($page->id);
        $this->assertEquals('published', $updatedPage->status);
        $this->assertNotNull($updatedPage->published_at);
    }

    public function testUpdateStageReturns422WithoutStatus(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft']);

        // Act
        $response = $this->putForSite("/api/pipeline/{$page->id}/stage", []);

        // Assert
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Status is required', $data['error']);
    }

    public function testUpdateStageReturns422WithInvalidStatus(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft']);

        // Act
        $response = $this->putForSite("/api/pipeline/{$page->id}/stage", [
            'status' => 'invalid-status'
        ]);

        // Assert
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid status', $data['error']);
    }

    public function testUpdateStageReturns404ForNonexistentPage(): void
    {
        // Act
        $response = $this->putForSite('/api/pipeline/99999/stage', [
            'status' => 'waiting_approval'
        ]);

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Page not found', $data['error']);
    }

    public function testMetricsReturnsStageCountsAndMetrics(): void
    {
        // Arrange
        $this->createPages(3, ['status' => 'draft']);
        $this->createPages(2, ['status' => 'waiting_approval']);
        $this->createPages(1, ['status' => 'scheduled']);
        $this->createPages(5, ['status' => 'published']);

        // Act
        $response = $this->getForSite('/api/pipeline/metrics');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('metrics', $data['data']);

        $metrics = $data['data']['metrics'];
        $this->assertArrayHasKey('stage_counts', $metrics);
        $this->assertEquals(3, $metrics['stage_counts']['draft']);
        $this->assertEquals(2, $metrics['stage_counts']['waiting_approval']);
        $this->assertEquals(1, $metrics['stage_counts']['scheduled']);
        $this->assertEquals(5, $metrics['stage_counts']['published']);

        $this->assertArrayHasKey('throughput', $metrics);
        $this->assertArrayHasKey('avg_time_per_stage', $metrics);
        $this->assertArrayHasKey('bottlenecks', $metrics);
    }

    public function testMetricsCalculatesThroughput(): void
    {
        // Arrange
        $thirtyOneDaysAgo = date('Y-m-d H:i:s', strtotime('-31 days'));
        $twentyDaysAgo = date('Y-m-d H:i:s', strtotime('-20 days'));

        $this->createPage([
            'status' => 'published',
            'published_at' => $twentyDaysAgo
        ]);
        $this->createPage([
            'status' => 'published',
            'published_at' => $thirtyOneDaysAgo
        ]);

        // Act
        $response = $this->getForSite('/api/pipeline/metrics');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $metrics = $data['data']['metrics'];
        $this->assertEquals(1, $metrics['throughput']);
    }

    public function testMetricsIdentifiesBottlenecks(): void
    {
        // Arrange - Create 8 draft pages (80% of 10 limit)
        $this->createPages(8, ['status' => 'draft']);

        // Act
        $response = $this->getForSite('/api/pipeline/metrics');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $metrics = $data['data']['metrics'];
        $this->assertContains('Draft', $metrics['bottlenecks']);
    }

    public function testMetricsIdentifiesMultipleBottlenecks(): void
    {
        // Arrange
        $this->createPages(8, ['status' => 'draft']); // 80% of 10
        $this->createPages(4, ['status' => 'waiting_approval']); // 80% of 5

        // Act
        $response = $this->getForSite('/api/pipeline/metrics');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $metrics = $data['data']['metrics'];
        $this->assertCount(2, $metrics['bottlenecks']);
        $this->assertContains('Draft', $metrics['bottlenecks']);
        $this->assertContains('In Review', $metrics['bottlenecks']);
    }

    public function testBulkUpdateStageUpdatesMultiplePages(): void
    {
        // Arrange
        $page1 = $this->createPage(['status' => 'draft']);
        $page2 = $this->createPage(['status' => 'draft']);
        $page3 = $this->createPage(['status' => 'draft']);

        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'page_ids' => [$page1->id, $page2->id, $page3->id],
            'status' => 'waiting_approval'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['success']);
        $this->assertEquals(3, $data['data']['updated']);
        $this->assertStringContainsString('3 pages updated', $data['data']['message']);

        // Verify database
        $this->assertDatabaseHas('pages', ['id' => $page1->id, 'status' => 'waiting_approval']);
        $this->assertDatabaseHas('pages', ['id' => $page2->id, 'status' => 'waiting_approval']);
        $this->assertDatabaseHas('pages', ['id' => $page3->id, 'status' => 'waiting_approval']);
    }

    public function testBulkUpdateStageReturns422WithoutPageIds(): void
    {
        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'status' => 'waiting_approval'
        ]);

        // Assert
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('No page IDs provided', $data['error']);
    }

    public function testBulkUpdateStageReturns422WithoutStatus(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft']);

        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'page_ids' => [$page->id]
        ]);

        // Assert
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Status is required', $data['error']);
    }

    public function testBulkUpdateStageReturns422WithInvalidStatus(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft']);

        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'page_ids' => [$page->id],
            'status' => 'invalid-status'
        ]);

        // Assert
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid status', $data['error']);
    }

    public function testBulkUpdateStageHandlesPartialFailures(): void
    {
        // Arrange
        $existingPage = $this->createPage(['status' => 'draft']);
        $nonExistentPageId = 99999;

        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'page_ids' => [$existingPage->id, $nonExistentPageId],
            'status' => 'waiting_approval'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Should update only the existing page
        $this->assertEquals(1, $data['data']['updated']);
        $this->assertDatabaseHas('pages', ['id' => $existingPage->id, 'status' => 'waiting_approval']);
    }

    public function testBulkUpdateStageToScheduledSetsScheduledAt(): void
    {
        // Arrange
        $page1 = $this->createPage(['status' => 'draft', 'scheduled_at' => null]);
        $page2 = $this->createPage(['status' => 'draft', 'scheduled_at' => null]);

        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'page_ids' => [$page1->id, $page2->id],
            'status' => 'scheduled'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $updatedPage1 = Page::find($page1->id);
        $updatedPage2 = Page::find($page2->id);

        $this->assertEquals('scheduled', $updatedPage1->status);
        $this->assertEquals('scheduled', $updatedPage2->status);
        $this->assertNotNull($updatedPage1->scheduled_at);
        $this->assertNotNull($updatedPage2->scheduled_at);
    }

    public function testBulkUpdateStageToPublishedSetsPublishedAt(): void
    {
        // Arrange
        $page1 = $this->createPage(['status' => 'draft', 'published_at' => null]);
        $page2 = $this->createPage(['status' => 'draft', 'published_at' => null]);

        // Act
        $response = $this->postForSite('/api/pipeline/bulk-update-stage', [
            'page_ids' => [$page1->id, $page2->id],
            'status' => 'published'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $updatedPage1 = Page::find($page1->id);
        $updatedPage2 = Page::find($page2->id);

        $this->assertEquals('published', $updatedPage1->status);
        $this->assertEquals('published', $updatedPage2->status);
        $this->assertNotNull($updatedPage1->published_at);
        $this->assertNotNull($updatedPage2->published_at);
    }

    public function testUpdateStageAllowsValidStatusTransitions(): void
    {
        // Test draft -> waiting_approval
        $page1 = $this->createPage(['status' => 'draft']);
        $response = $this->putForSite("/api/pipeline/{$page1->id}/stage", ['status' => 'waiting_approval']);
        $this->assertEquals(200, $response->getStatusCode());

        // Test waiting_approval -> scheduled
        $page2 = $this->createPage(['status' => 'waiting_approval']);
        $response = $this->putForSite("/api/pipeline/{$page2->id}/stage", ['status' => 'scheduled']);
        $this->assertEquals(200, $response->getStatusCode());

        // Test scheduled -> published
        $page3 = $this->createPage(['status' => 'scheduled']);
        $response = $this->putForSite("/api/pipeline/{$page3->id}/stage", ['status' => 'published']);
        $this->assertEquals(200, $response->getStatusCode());

        // Test waiting_approval -> draft (backward)
        $page4 = $this->createPage(['status' => 'waiting_approval']);
        $response = $this->putForSite("/api/pipeline/{$page4->id}/stage", ['status' => 'draft']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexFiltersBySite(): void
    {
        // Arrange
        $site2 = $this->createSite();

        $page1 = $this->createPage(['status' => 'draft', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['status' => 'draft', 'site_id' => $site2->id]);

        // Act
        $response = $this->getForSite('/api/pipeline');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $draftPageIds = array_column($data['data']['stages']['draft']['cards'], 'id');
        $this->assertContains($page1->id, $draftPageIds);
        $this->assertNotContains($page2->id, $draftPageIds);
    }

    public function testMetricsFiltersBySite(): void
    {
        // Arrange
        $site2 = $this->createSite();

        $this->createPages(3, ['status' => 'draft', 'site_id' => $this->siteId]);
        $this->createPages(5, ['status' => 'draft', 'site_id' => $site2->id]);

        // Act
        $response = $this->getForSite('/api/pipeline/metrics');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $metrics = $data['data']['metrics'];
        $this->assertEquals(3, $metrics['stage_counts']['draft']);
    }
}