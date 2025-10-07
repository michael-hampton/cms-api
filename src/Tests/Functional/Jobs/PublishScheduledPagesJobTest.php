<?php

namespace App\Tests\Functional\Jobs;

use App\Framework\Database\Database;
use App\Jobs\PublishScheduledPagesJob;
use DateTime;
use PHPUnit\Framework\TestCase;

class PublishScheduledPagesJobTest extends TestCase
{
    private $database;
    private $job;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup in-memory SQLite database for testing
        $this->database = Database::getInstance([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Run migrations to create tables
        $this->createTables();

        // Create job instance
        $this->job = new PublishScheduledPagesJob();
    }

    protected function tearDown(): void
    {
        // Clean up database
        $this->database->exec('DROP TABLE IF EXISTS pages');
        $this->database->exec('DROP TABLE IF EXISTS page_metadata');

        parent::tearDown();
    }

    private function createTables(): void
    {
        // Create pages table
        $this->database->exec('
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                status VARCHAR(50) DEFAULT "draft",
                published_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create page_metadata table
        $this->database->exec('
            CREATE TABLE page_metadata (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id INTEGER NOT NULL,
                publish_date DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            )
        ');
    }

    private function createDraftPage(string $title, ?string $publishDate = null): int
    {
        $stmt = $this->database->query('
            INSERT INTO pages (title, content, status, created_at, updated_at)
            VALUES (?, ?, "draft", datetime("now"), datetime("now"))
        ', [$title, "Content for {$title}"]);
        $pageId = (int) $this->database->lastInsertId();

        if ($publishDate !== null) {
            $stmt = $this->database->query('
                INSERT INTO page_metadata (page_id, publish_date, created_at, updated_at)
                VALUES (?, ?, datetime("now"), datetime("now"))
            ', [$pageId, $publishDate]);
        }

        return $pageId;
    }

    private function getPageById(int $id): ?array
    {
        $stmt = $this->database->query('SELECT * FROM pages WHERE id = ?', [$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function testHandleMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->job, 'handle'),
            'PublishScheduledPagesJob must have a handle method'
        );
    }

    public function testHandleReturnsVoid(): void
    {
        $reflection = new \ReflectionMethod($this->job, 'handle');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType, 'handle method must have a return type');
        $this->assertEquals('void', $returnType->getName(), 'handle method must return void');
    }

    public function testJobExtendsBaseJobClass(): void
    {
        $this->assertInstanceOf(
            \App\Framework\Queue\Job::class,
            $this->job,
            'PublishScheduledPagesJob must extend Job base class'
        );
    }

    public function testJobImplementsJobInterface(): void
    {
        $this->assertInstanceOf(
            \App\Framework\Queue\JobInterface::class,
            $this->job,
            'PublishScheduledPagesJob must implement JobInterface'
        );
    }

    public function testPublishesPageWithPastPublishDate(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');

        $pageId = $this->createDraftPage(
            'Past Scheduled Page',
            $pastDate->format('Y-m-d H:i:s')
        );

        $this->job->handle();

        $page = $this->getPageById($pageId);

        $this->assertEquals('published', $page['status']);
        $this->assertNotNull($page['published_at']);
    }

    public function testDoesNotPublishPageWithFuturePublishDate(): void
    {
        $now = new DateTime();
        $futureDate = (clone $now)->modify('+1 hour');

        $pageId = $this->createDraftPage(
            'Future Scheduled Page',
            $futureDate->format('Y-m-d H:i:s')
        );

        $this->job->handle();

        $page = $this->getPageById($pageId);

        $this->assertEquals('draft', $page['status']);
        $this->assertNull($page['published_at']);
    }

    public function testPublishesPageWithExactCurrentTime(): void
    {
        $now = new DateTime();

        $pageId = $this->createDraftPage(
            'Current Time Page',
            $now->format('Y-m-d H:i:s')
        );

        $this->job->handle();

        $page = $this->getPageById($pageId);

        $this->assertEquals('published', $page['status']);
        $this->assertNotNull($page['published_at']);
    }

    public function testPublishesMultipleDuePages(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-2 hours');

        $pageId1 = $this->createDraftPage('Page 1', $pastDate->format('Y-m-d H:i:s'));
        $pageId2 = $this->createDraftPage('Page 2', $pastDate->format('Y-m-d H:i:s'));
        $pageId3 = $this->createDraftPage('Page 3', $pastDate->format('Y-m-d H:i:s'));

        $this->job->handle();

        $page1 = $this->getPageById($pageId1);
        $page2 = $this->getPageById($pageId2);
        $page3 = $this->getPageById($pageId3);

        $this->assertEquals('published', $page1['status']);
        $this->assertEquals('published', $page2['status']);
        $this->assertEquals('published', $page3['status']);
    }

    public function testDoesNotPublishAlreadyPublishedPages(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');

        // Create a page that's already published
        $stmt = $this->database->query('
            INSERT INTO pages (title, content, status, published_at, created_at, updated_at)
            VALUES (?, ?, "published", ?, datetime("now"), datetime("now"))
        ', ['Already Published', 'Content', $now->format('Y-m-d H:i:s')]);
        $pageId = (int) $this->database->lastInsertId();

        // Add metadata with past publish date
        $stmt = $this->database->query('
            INSERT INTO page_metadata (page_id, publish_date, created_at, updated_at)
            VALUES (?, ?, datetime("now"), datetime("now"))
        ', [$pageId, $pastDate->format('Y-m-d H:i:s')]);

        $originalPublishedAt = $this->getPageById($pageId)['published_at'];

        $this->job->handle();

        $page = $this->getPageById($pageId);

        // Should remain published with original timestamp
        $this->assertEquals('published', $page['status']);
        $this->assertEquals($originalPublishedAt, $page['published_at']);
    }

    public function testDoesNotPublishDraftPagesWithoutMetadata(): void
    {
        $pageId = $this->createDraftPage('No Metadata Page', null);

        $this->job->handle();

        $page = $this->getPageById($pageId);

        $this->assertEquals('draft', $page['status']);
        $this->assertNull($page['published_at']);
    }

    public function testHandlesEmptyDatabase(): void
    {
        // No pages in database
        $this->job->handle();

        // Should not throw any errors
        $this->assertTrue(true);
    }

    public function testSetsPublishedAtToCurrentTime(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');

        $pageId = $this->createDraftPage(
            'Timestamp Test Page',
            $pastDate->format('Y-m-d H:i:s')
        );

        $beforeExecution = new DateTime();
        $this->job->handle();
        $afterExecution = new DateTime();

        $page = $this->getPageById($pageId);
        $publishedAt = new DateTime($page['published_at']);

        // Published time should be between before and after execution
        $this->assertGreaterThanOrEqual(
            $beforeExecution->getTimestamp(),
            $publishedAt->getTimestamp()
        );
        $this->assertLessThanOrEqual(
            $afterExecution->getTimestamp(),
            $publishedAt->getTimestamp()
        );
    }

    public function testPublishesOnlyDraftPages(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');

        // Create draft page
        $draftPageId = $this->createDraftPage(
            'Draft Page',
            $pastDate->format('Y-m-d H:i:s')
        );

        // Create archived page with metadata
        $stmt = $this->database->query('
            INSERT INTO pages (title, content, status, created_at, updated_at)
            VALUES (?, ?, "archived", datetime("now"), datetime("now"))
        ', ['Archived Page', 'Content']);
        $archivedPageId = (int) $this->database->lastInsertId();

        $stmt = $this->database->query('
            INSERT INTO page_metadata (page_id, publish_date, created_at, updated_at)
            VALUES (?, ?, datetime("now"), datetime("now"))
        ', [$archivedPageId, $pastDate->format('Y-m-d H:i:s')]);

        $this->job->handle();

        $draftPage = $this->getPageById($draftPageId);
        $archivedPage = $this->getPageById($archivedPageId);

        $this->assertEquals('published', $draftPage['status']);
        $this->assertEquals('archived', $archivedPage['status']);
    }

    public function testMixedScenario(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');
        $futureDate = (clone $now)->modify('+1 hour');

        // Should be published (draft + past date)
        $shouldPublishId = $this->createDraftPage(
            'Should Publish',
            $pastDate->format('Y-m-d H:i:s')
        );

        // Should not be published (draft + future date)
        $futurePageId = $this->createDraftPage(
            'Future Page',
            $futureDate->format('Y-m-d H:i:s')
        );

        // Should not be published (draft + no metadata)
        $noMetadataId = $this->createDraftPage('No Metadata', null);

        $this->job->handle();

        $shouldPublish = $this->getPageById($shouldPublishId);
        $futurePage = $this->getPageById($futurePageId);
        $noMetadata = $this->getPageById($noMetadataId);

        $this->assertEquals('published', $shouldPublish['status']);
        $this->assertNotNull($shouldPublish['published_at']);

        $this->assertEquals('draft', $futurePage['status']);
        $this->assertNull($futurePage['published_at']);

        $this->assertEquals('draft', $noMetadata['status']);
        $this->assertNull($noMetadata['published_at']);
    }

    public function testJobHasCorrectRetryProperties(): void
    {
        $this->assertObjectHasProperty('tries', $this->job);
        $this->assertObjectHasProperty('timeout', $this->job);
        $this->assertObjectHasProperty('delay', $this->job);
    }

    public function testJobHasFailedMethod(): void
    {
        $this->assertTrue(
            method_exists($this->job, 'failed'),
            'Job must have a failed method for error handling'
        );
    }

    public function testPublishedAtUsesCorrectDateFormat(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');

        $pageId = $this->createDraftPage(
            'Format Test Page',
            $pastDate->format('Y-m-d H:i:s')
        );

        $this->job->handle();

        $page = $this->getPageById($pageId);

        // Check that published_at is in correct format (Y-m-d H:i:s)
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $page['published_at']
        );
    }

    public function testJobCanHandleLargeNumberOfPages(): void
    {
        $now = new DateTime();
        $pastDate = (clone $now)->modify('-1 hour');

        // Create 100 pages
        $pageIds = [];
        for ($i = 0; $i < 100; $i++) {
            $pageIds[] = $this->createDraftPage(
                "Page {$i}",
                $pastDate->format('Y-m-d H:i:s')
            );
        }

        $this->job->handle();

        // Verify all were published
        $publishedCount = 0;
        foreach ($pageIds as $pageId) {
            $page = $this->getPageById($pageId);
            if ($page['status'] === 'published') {
                $publishedCount++;
            }
        }

        $this->assertEquals(100, $publishedCount);
    }
}