<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\NewsletterIssueStatus;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Models\NewsletterIssue;
use App\Repositories\Newsletters\NewsletterIssueRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\NewsletterIssueService;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Newsletter\Validation\BlockPayloadValidator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * Unit tests for NewsletterIssueService.
 *
 * All collaborators are mocked. databaseMock::transaction() is wired to
 * immediately invoke its closure, which is the standard test pattern used
 * throughout this codebase.
 */
class NewsletterIssueServiceTest extends FunctionalTestCase
{
    private NewsletterIssueRepository|MockInterface $issueRepository;
    private NewsletterRepository|MockInterface $newsletterRepository;
    private NewsletterSendService|MockInterface $sendService;
    private BlockPayloadValidator|MockInterface $blockPayloadValidator;
    private Logger|MockInterface $logger;
    private Database|MockInterface $databaseMock;

    private NewsletterIssueService $service;

    public function test_create_issue_persists_draft_with_correct_fields(): void
    {
        $newsletter = $this->makeNewsletter(['title' => 'Weekly Digest']);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $expectedIssue = $this->makeIssue(['id' => 10, 'status' => 'draft', 'subject' => 'Weekly Digest']);
        $this->issueRepository->expects('create')
            ->withArgs(function (array $data) {
                return $data['newsletter_id'] === 1
                    && $data['site_id'] === 5
                    && $data['subject'] === 'Weekly Digest'
                    && $data['status'] === NewsletterIssueStatus::Draft->value
                    && $data['content_blocks'] === null
                    && $data['scheduled_at'] === null;
            })
            ->andReturn($expectedIssue);

        $this->logger->allows('info');

        $result = $this->service->createIssue(1, 5, []);

        $this->assertSame($expectedIssue, $result);
    }

    private function makeNewsletter(array $attributes = []): Newsletter|MockInterface
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();

        $newsletter->id = $attributes['id'] ?? 1;
        $newsletter->site_id = $attributes['site_id'] ?? 5;
        $newsletter->title = $attributes['title'] ?? 'Test Newsletter';
        $newsletter->content_blocks = $attributes['content_blocks'] ?? null;
        $newsletter->content_type = $attributes['content_type'] ?? 'manual';

        return $newsletter;
    }

    // =========================================================================
    // createIssue()
    // =========================================================================

    /**
     * Register a strict once() transaction expectation.
     * Returns a callable that returns true after the transaction fires.
     *
     *   $called = $this->expectTransaction();
     *   $service->doSomething();
     *   $this->assertTrue($called());
     */
    private function expectTransaction(): callable
    {
        $wasCalled = false;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $fn) use (&$wasCalled) {
                $wasCalled = true;
                return $fn();
            });

        return fn() => $wasCalled;
    }

    private function makeIssue(array $attributes = []): NewsletterIssue|MockInterface
    {
        $issue = Mockery::mock(NewsletterIssue::class)->makePartial();

        $issue->id = $attributes['id'] ?? 1;
        $issue->site_id = $attributes['site_id'] ?? 5;
        $issue->newsletter_id = $attributes['newsletter_id'] ?? 1;
        $issue->subject = $attributes['subject'] ?? 'Test Issue';
        $issue->status = $attributes['status'] ?? 'draft';
        $issue->content_blocks = $attributes['content_blocks'] ?? null;
        $issue->send_id = $attributes['send_id'] ?? null;
        $issue->sent_at = $attributes['sent_at'] ?? null;

        // Wire the status helpers from the real model logic unless explicitly overridden
        if (!isset($attributes['content_blocks'])) {
            $issue->allows('isSent')->andReturnUsing(
                fn() => $issue->status === NewsletterIssueStatus::Sent->value
            );
        }

        return $issue;
    }

    public function test_create_issue_uses_provided_subject_over_newsletter_title(): void
    {
        $newsletter = $this->makeNewsletter(['title' => 'Newsletter Title']);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['subject'] === 'Custom Subject')
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $this->service->createIssue(1, 5, ['subject' => 'Custom Subject']);
        $this->assertTrue(true);
    }

    public function test_create_issue_validates_blocks_when_provided(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $blocks = [['type' => 'text', 'data' => ['content' => 'Hello']]];
        $this->blockPayloadValidator->expects('validate')->with($blocks)->once();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['content_blocks'] === $blocks)
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $this->service->createIssue(1, 5, ['content_blocks' => $blocks]);
        $this->assertTrue(true);
    }

    public function test_create_issue_skips_validation_when_no_blocks(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $this->blockPayloadValidator->expects('validate')->never();

        $this->issueRepository->expects('create')->andReturn($this->makeIssue());
        $this->logger->allows('info');

        $this->service->createIssue(1, 5, []);
        $this->assertTrue(true);
    }

    public function test_create_issue_throws_runtime_exception_when_newsletter_not_found(): void
    {
        $this->newsletterRepository->expects('find')->with(99)->andReturn(null);

        $this->expectTransaction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Newsletter 99 not found');

        $this->service->createIssue(99, 5, []);
    }

    public function test_create_issue_throws_runtime_exception_when_newsletter_belongs_to_different_site(): void
    {
        $newsletter = $this->makeNewsletter(['site_id' => 99]);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $this->expectException(\RuntimeException::class);

        $this->service->createIssue(1, 5, []);
    }

    public function test_create_issue_throws_invalid_argument_when_block_validation_fails(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $badBlocks = [['type' => 'unknown-type']];
        $this->blockPayloadValidator->expects('validate')
            ->with($badBlocks)
            ->andThrow(new \InvalidArgumentException("Unknown block type 'unknown-type' at index 0."));

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createIssue(1, 5, ['content_blocks' => $badBlocks]);
    }

    public function test_create_issue_persists_scheduled_at(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['scheduled_at'] === '2026-06-01 08:00:00')
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $this->service->createIssue(1, 5, ['scheduled_at' => '2026-06-01 08:00:00']);
        $this->assertTrue(true);
    }

    // =========================================================================
    // sendIssue()
    // =========================================================================

    public function test_create_issue_is_wrapped_in_a_transaction(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->allows('find')->andReturn($newsletter);
        $this->issueRepository->allows('create')->andReturn($this->makeIssue());
        $this->logger->allows('info');

        $transactionCalled = false;
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $fn) use (&$transactionCalled) {
                $transactionCalled = true;
                return $fn();
            });

        $this->service->createIssue(1, 5, []);

        $this->assertTrue($transactionCalled, 'createIssue must execute inside a transaction');
        $this->assertTrue(true);
    }

    public function test_send_issue_delegates_to_send_service_and_transitions_to_sent(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->expectTransaction();

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $sendResult = ['success' => true, 'send_id' => 55, 'recipients' => 100, 'failed' => 0];
        $this->sendService->expects('sendNewsletter')
            ->withArgs(fn($nl, $siteId, $actor) => $siteId === 5)
            ->andReturn($sendResult);

        $this->issueRepository->expects('update')
            ->withArgs(function (int $id, array $data) {
                return $id === 7
                    && $data['status'] === NewsletterIssueStatus::Sent->value
                    && $data['send_id'] === 55
                    && isset($data['sent_at']);
            })
            ->andReturn($this->makeIssue(['status' => 'sent']));

        $this->logger->allows('info');

        // Expect the domain event to fire
        $firedEvents = [];
        $originalEventDispatcher = null;
        // We test event emission without executing the listener (per contract rules)
        // — use a simple closure-based fake if your framework supports it,
        //   otherwise assert via expectsEvents() in integration context.
        // Here we verify the method completes without exception and returns correctly.

        $result = $this->service->sendIssue(7, 5);

        $this->assertTrue($result['success']);
        $this->assertEquals(7, $result['issue_id']);
        $this->assertEquals(55, $result['send_id']);
    }

    public function test_send_issue_throws_runtime_exception_when_issue_not_found(): void
    {
        $this->issueRepository->expects('find')->with(999)->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Issue 999 not found');

        $this->service->sendIssue(999, 5);
    }

    public function test_send_issue_throws_runtime_exception_when_issue_belongs_to_different_site(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'site_id' => 99]);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectException(\RuntimeException::class);

        $this->service->sendIssue(7, 5);
    }

    public function test_send_issue_throws_domain_exception_when_issue_already_sent(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'sent']);
        $issue->allows('isSent')->andReturn(true);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already been sent/');

        $this->service->sendIssue(7, 5);
    }

    public function test_send_issue_throws_runtime_exception_when_newsletter_not_found(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'newsletter_id' => 99, 'status' => 'draft']);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(99)->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->service->sendIssue(7, 5);
    }

    public function test_send_issue_returns_early_when_send_service_reports_total_failure(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->sendService->expects('sendNewsletter')
            ->andReturn(['success' => false, 'error' => 'No eligible recipients']);

        // update() must NOT be called — the issue should remain in its current status
        $this->issueRepository->expects('update')->never();

        $result = $this->service->sendIssue(7, 5);

        $this->assertFalse($result['success']);
    }

    public function test_send_issue_transitions_issue_even_on_partial_failure(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->expectTransaction();

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        // Partial failure means some emails sent, some failed
        $sendResult = ['success' => false, 'partial_failure' => true, 'send_id' => 55, 'recipients' => 80, 'failed' => 20];
        $this->sendService->expects('sendNewsletter')->andReturn($sendResult);

        $this->issueRepository->expects('update')
            ->withArgs(fn($id, $data) => $data['status'] === NewsletterIssueStatus::Sent->value)
            ->andReturn($this->makeIssue(['status' => 'sent']));

        $this->logger->allows('info');

        $result = $this->service->sendIssue(7, 5);

        $this->assertEquals(7, $result['issue_id']);
    }

    public function test_send_issue_uses_newsletter_blocks_when_issue_has_none(): void
    {
        $issue = $this->makeIssue([
            'id' => 7,
            'status' => 'draft',
            'newsletter_id' => 1,
            'content_blocks' => null,
        ]);

        $this->expectTransaction();

        // Real Newsletter so property reads are reliable
        $newsletter = new Newsletter();
        $newsletter->id = 1;
        $newsletter->site_id = 5;
        $newsletter->title = 'Test Newsletter';
        $newsletter->content_blocks = [['type' => 'text', 'data' => ['content' => 'Newsletter fallback']]];
        $newsletter->content_type = 'manual';

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        // When the issue has no blocks, prepareNewsletterForIssue returns the
        // original newsletter object unchanged (no clone).
        $this->sendService->expects('sendNewsletter')
            ->withArgs(function (Newsletter $nl) use ($newsletter) {
                return $nl === $newsletter;
            })
            ->andReturn(['success' => true, 'send_id' => 1, 'recipients' => 10, 'failed' => 0]);

        $this->issueRepository->allows('update')->andReturn($this->makeIssue(['status' => 'sent']));
        $this->logger->allows('info');

        $this->service->sendIssue(7, 5);
        $this->assertTrue(true);
    }

    // =========================================================================
    // updateIssue()
    // =========================================================================

    public function test_send_issue_transition_is_wrapped_in_transaction(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->issueRepository->allows('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->allows('find')->with(1)->andReturn($newsletter);
        $this->sendService->allows('sendNewsletter')
            ->andReturn(['success' => true, 'send_id' => 1, 'recipients' => 10, 'failed' => 0]);
        $this->issueRepository->allows('update')->andReturn($this->makeIssue(['status' => 'sent']));
        $this->logger->allows('info');

        $transactionCalled = false;
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $fn) use (&$transactionCalled) {
                $transactionCalled = true;
                return $fn();
            });

        $this->service->sendIssue(7, 5);

        $this->assertTrue($transactionCalled, 'sendIssue must wrap the status transition in a transaction');
    }

    public function test_update_issue_persists_changes_for_draft_issue(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft']);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectTransaction();

        $updatedIssue = $this->makeIssue(['subject' => 'New Subject', 'status' => 'ready']);
        $this->issueRepository->expects('update')
            ->withArgs(fn($id, $data) => $id === 7 && $data['subject'] === 'New Subject')
            ->andReturn($updatedIssue);

        $result = $this->service->updateIssue(7, 5, ['subject' => 'New Subject', 'status' => 'ready']);

        $this->assertSame($updatedIssue, $result);
    }

    public function test_update_issue_throws_domain_exception_for_sent_issue(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'sent']);
        $issue->allows('isSent')->andReturn(true);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectTransaction();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        $this->service->updateIssue(7, 5, ['subject' => 'Attempt to change sent issue']);
    }

    public function test_update_issue_throws_runtime_exception_when_not_found(): void
    {
        $this->issueRepository->expects('find')->with(99)->andReturn(null);

        $this->expectTransaction();

        $this->expectException(\RuntimeException::class);

        $this->service->updateIssue(99, 5, []);
    }

    public function test_update_issue_validates_blocks_when_provided(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft']);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectTransaction();

        $blocks = [['type' => 'heading', 'data' => ['text' => 'Hi']]];
        $this->blockPayloadValidator->expects('validate')->with($blocks)->once();
        $this->issueRepository->expects('update')->andReturn($this->makeIssue());

        $this->service->updateIssue(7, 5, ['content_blocks' => $blocks]);
        $this->assertTrue(true);
    }

    // =========================================================================
    // listIssues()
    // =========================================================================

    public function test_update_issue_is_wrapped_in_transaction(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft']);
        $this->issueRepository->allows('find')->andReturn($issue);
        $this->issueRepository->allows('update')->andReturn($this->makeIssue());

        $transactionCalled = false;
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $fn) use (&$transactionCalled) {
                $transactionCalled = true;
                return $fn();
            });

        $this->service->updateIssue(7, 5, ['subject' => 'x']);

        $this->assertTrue($transactionCalled, 'updateIssue must execute inside a transaction');
        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_list_issues_returns_array_from_repository(): void
    {
        $issues = collect([
            $this->makeIssue(['id' => 1]),
            $this->makeIssue(['id' => 2]),
        ]);

        $this->issueRepository->expects('findByNewsletter')
            ->with(10, 5)
            ->andReturn($issues);

        $result = $this->service->listIssues(10, 5);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->issueRepository = Mockery::mock(NewsletterIssueRepository::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->sendService = Mockery::mock(NewsletterSendService::class);
        $this->blockPayloadValidator = Mockery::mock(BlockPayloadValidator::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->databaseMock = Mockery::mock(Database::class);


        $this->service = new NewsletterIssueService(
            $this->issueRepository,
            $this->newsletterRepository,
            $this->sendService,
            $this->blockPayloadValidator,
            $this->logger,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Allow transaction() to be called any number of times.
     * Call this in every test whose code path reaches database->transaction()
     * but where the transaction itself is not what the test is verifying.
     */
    private function allowTransaction(): void
    {
        $this->databaseMock->allows('transaction')
            ->andReturnUsing(fn(callable $fn) => $fn());
    }

}