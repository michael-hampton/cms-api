<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\DTO\Newsletters\IssueManualSendDTO;
use App\DTO\Newsletters\NewsletterIssueDTO;
use App\Enums\Newsletters\NewsletterIssueStatus;
use App\Events\Newsletters\NewsletterIssueSent;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Models\NewsletterIssue;
use App\Repositories\Newsletters\NewsletterIssueRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\NewsletterIssueService;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Newsletter\Validation\BlockPayloadValidator;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;

/**
 * Unit tests for NewsletterIssueService.
 *
 * All collaborators are mocked. databaseMock::transaction() is wired to
 * immediately invoke its closure — the standard pattern in this codebase.
 *
 * Rule: test that events are emitted, do NOT test listener execution.
 * Rule: test transaction usage, do NOT test framework internals.
 */
class NewsletterIssueServiceTest extends UnitTestCase
{
    private NewsletterIssueRepository|MockInterface $issueRepository;
    private NewsletterRepository|MockInterface $newsletterRepository;
    private NewsletterSendService|MockInterface $sendService;
    private BlockPayloadValidator|MockInterface $blockPayloadValidator;
    private Logger|MockInterface $logger;
    private Database|MockInterface $databaseMock;
    private CapturingEventDispatcher $events;

    private NewsletterIssueService $service;

    // =========================================================================
    // createIssue()
    // =========================================================================

    public function test_create_issue_persists_draft_with_correct_fields(): void
    {
        $newsletter = $this->makeNewsletter(['title' => 'Weekly Digest']);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->allows('getMaxIssueNumber')->with(1)->andReturn(0);

        $txCalled = $this->expectTransaction();

        $expectedIssue = $this->makeIssue(['id' => 10, 'status' => 'draft', 'subject' => 'Weekly Digest']);
        $this->issueRepository->expects('create')
            ->withArgs(function (array $data) {
                return $data['newsletter_id'] === 1
                    && $data['site_id'] === 5
                    && $data['subject'] === 'Weekly Digest'
                    && $data['issue_number'] === 1
                    && $data['status'] === NewsletterIssueStatus::Draft->value
                    && $data['content_blocks'] === null
                    && $data['snapshot_json'] === null
                    && $data['scheduled_at'] === null;
            })
            ->andReturn($expectedIssue);

        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray([]);
        $result = $this->service->createIssue(1, 5, $dto);

        $this->assertSame($expectedIssue, $result);
        $this->assertTrue($txCalled());
    }

    public function test_create_issue_uses_provided_subject_over_newsletter_title(): void
    {
        $newsletter = $this->makeNewsletter(['title' => 'Newsletter Title']);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->expects('getMaxIssueNumber')->with(1)->andReturn(2);

        $this->expectTransaction();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['subject'] === 'Custom Subject' && $data['issue_number'] === 3)
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray(['subject' => 'Custom Subject']);
        $this->service->createIssue(1, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_create_issue_validates_blocks_when_provided(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->expects('getMaxIssueNumber')->with(1)->andReturn(0);

        $this->expectTransaction();

        $blocks = [['type' => 'text', 'data' => ['content' => 'Hello']]];
        $this->blockPayloadValidator->expects('validate')->with($blocks)->once();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['content_blocks'] === $blocks)
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray(['content_blocks' => $blocks]);
        $this->service->createIssue(1, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_create_issue_skips_validation_when_no_blocks(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->expects('getMaxIssueNumber')->with(1)->andReturn(0);

        $this->expectTransaction();

        $this->blockPayloadValidator->expects('validate')->never();

        $this->issueRepository->expects('create')->andReturn($this->makeIssue());
        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray([]);
        $this->service->createIssue(1, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_create_issue_persists_snapshot_json_when_provided(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->expects('getMaxIssueNumber')->with(1)->andReturn(0);

        $this->expectTransaction();

        $snapshot = ['layout' => ['layout_id' => 3], 'blocks' => [], 'metadata' => ['title' => 'April']];

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['snapshot_json'] === $snapshot)
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray(['snapshot_json' => $snapshot]);
        $this->service->createIssue(1, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_create_issue_increments_issue_number_per_newsletter(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        // Simulate 4 existing issues
        $this->issueRepository->expects('getMaxIssueNumber')->with(1)->andReturn(4);

        $this->expectTransaction();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['issue_number'] === 5)
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray([]);
        $this->service->createIssue(1, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_create_issue_throws_runtime_exception_when_newsletter_not_found(): void
    {
        $this->newsletterRepository->expects('find')->with(99)->andReturn(null);

        $this->expectTransaction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Newsletter 99 not found');

        $dto = NewsletterIssueDTO::fromArray([]);
        $this->service->createIssue(99, 5, $dto);
    }

    public function test_create_issue_throws_runtime_exception_when_newsletter_belongs_to_different_site(): void
    {
        $newsletter = $this->makeNewsletter(['site_id' => 99]);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->expectTransaction();

        $this->expectException(\RuntimeException::class);

        $dto = NewsletterIssueDTO::fromArray([]);
        $this->service->createIssue(1, 5, $dto);
    }

    public function test_create_issue_throws_invalid_argument_when_block_validation_fails(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->allows('getMaxIssueNumber')->with(1)->andReturn(0);

        $this->expectTransaction();

        $badBlocks = [['type' => 'unknown-type']];
        $this->blockPayloadValidator->expects('validate')
            ->with($badBlocks)
            ->andThrow(new \InvalidArgumentException("Unknown block type 'unknown-type' at index 0."));

        $this->expectException(\InvalidArgumentException::class);

        $dto = NewsletterIssueDTO::fromArray(['content_blocks' => $badBlocks]);
        $this->service->createIssue(1, 5, $dto);
    }

    public function test_create_issue_persists_scheduled_at(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->issueRepository->expects('getMaxIssueNumber')->with(1)->andReturn(0);

        $this->expectTransaction();

        $this->issueRepository->expects('create')
            ->withArgs(fn($data) => $data['scheduled_at'] === '2026-06-01 08:00:00')
            ->andReturn($this->makeIssue());

        $this->logger->allows('info');

        $dto = NewsletterIssueDTO::fromArray(['scheduled_at' => '2026-06-01 08:00:00']);
        $this->service->createIssue(1, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_create_issue_is_wrapped_in_a_transaction(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->newsletterRepository->allows('find')->andReturn($newsletter);
        $this->issueRepository->allows('getMaxIssueNumber')->andReturn(0);
        $this->issueRepository->allows('create')->andReturn($this->makeIssue());
        $this->logger->allows('info');

        $transactionCalled = false;
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $fn) use (&$transactionCalled) {
                $transactionCalled = true;
                return $fn();
            });

        $dto = NewsletterIssueDTO::fromArray([]);
        $this->service->createIssue(1, 5, $dto);

        $this->assertTrue($transactionCalled, 'createIssue must execute inside a transaction');
    }

    // =========================================================================
    // getIssueSnapshot() / revert
    // =========================================================================

    public function test_get_issue_snapshot_returns_issue_and_snapshot_json(): void
    {
        $snapshot = ['layout' => [], 'blocks' => [], 'metadata' => ['title' => 'May']];
        $issue = $this->makeIssue([
            'id' => 3,
            'newsletter_id' => 1,
            'site_id' => 5,
            'snapshot_json' => $snapshot,
        ]);

        $this->issueRepository->expects('find')->with(3)->andReturn($issue);

        $result = $this->service->getIssueSnapshot(1, 3, 5);

        $this->assertSame($issue, $result['issue']);
        $this->assertSame($snapshot, $result['snapshot_json']);
    }

    public function test_get_issue_snapshot_throws_when_issue_not_found(): void
    {
        $this->issueRepository->expects('find')->with(99)->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Issue 99 not found');

        $this->service->getIssueSnapshot(1, 99, 5);
    }

    public function test_get_issue_snapshot_throws_when_issue_belongs_to_different_site(): void
    {
        $issue = $this->makeIssue(['id' => 3, 'site_id' => 99, 'newsletter_id' => 1]);
        $this->issueRepository->expects('find')->with(3)->andReturn($issue);

        $this->expectException(\RuntimeException::class);

        $this->service->getIssueSnapshot(1, 3, 5);
    }

    public function test_get_issue_snapshot_throws_when_issue_belongs_to_different_newsletter(): void
    {
        $issue = $this->makeIssue(['id' => 3, 'site_id' => 5, 'newsletter_id' => 99]);
        $this->issueRepository->expects('find')->with(3)->andReturn($issue);

        $this->expectException(\RuntimeException::class);

        $this->service->getIssueSnapshot(1, 3, 5);
    }

    public function test_get_issue_snapshot_returns_null_snapshot_json_when_none_saved(): void
    {
        $issue = $this->makeIssue([
            'id' => 3,
            'newsletter_id' => 1,
            'site_id' => 5,
            'snapshot_json' => null,
        ]);
        $this->issueRepository->expects('find')->with(3)->andReturn($issue);

        $result = $this->service->getIssueSnapshot(1, 3, 5);

        $this->assertNull($result['snapshot_json']);
    }

    public function test_get_issue_snapshot_does_not_mutate_newsletter(): void
    {
        // This test asserts read-only behaviour: no writes occur.
        $issue = $this->makeIssue(['id' => 3, 'newsletter_id' => 1, 'site_id' => 5]);
        $this->issueRepository->expects('find')->with(3)->andReturn($issue);

        // Neither the newsletter repo nor the issue repo update() should be called
        $this->newsletterRepository->expects('update')->never();
        $this->issueRepository->expects('update')->never();

        $this->service->getIssueSnapshot(1, 3, 5);
        $this->assertTrue(true);
    }

    // =========================================================================
    // sendIssue()
    // =========================================================================

    public function test_send_issue_delegates_to_send_service_and_transitions_to_sent(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $txCalled = $this->expectTransaction();

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

        $result = $this->service->sendIssue(7, 5);

        $this->assertTrue($result['success']);
        $this->assertEquals(7, $result['issue_id']);
        $this->assertEquals(55, $result['send_id']);
        $this->assertTrue($txCalled());
        $this->events->assertDispatched(
            NewsletterIssueSent::class,
            fn(NewsletterIssueSent $event): bool => $event->issue === $issue
                && $event->sendResult === $sendResult
        );
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

        // update() must NOT be called — issue stays in current status
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

        $sendResult = ['success' => false, 'partial_failure' => true, 'send_id' => 55, 'recipients' => 80, 'failed' => 20];
        $this->sendService->expects('sendNewsletter')->andReturn($sendResult);

        $this->issueRepository->expects('update')
            ->withArgs(fn($id, $data) => $data['status'] === NewsletterIssueStatus::Sent->value)
            ->andReturn($this->makeIssue(['status' => 'sent']));

        $this->logger->allows('info');

        $result = $this->service->sendIssue(7, 5);

        $this->assertEquals(7, $result['issue_id']);
    }

    public function test_send_issue_uses_newsletter_content_when_issue_has_no_blocks(): void
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
        $newsletter->content_blocks = [['type' => 'text', 'data' => ['content' => 'Fallback']]];
        $newsletter->content_type = 'manual';

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        // No blocks on issue → prepareNewsletterForIssue returns the original object unchanged
        $this->sendService->expects('sendNewsletter')
            ->withArgs(fn(Newsletter $nl) => $nl === $newsletter)
            ->andReturn(['success' => true, 'send_id' => 1, 'recipients' => 10, 'failed' => 0]);

        $this->issueRepository->allows('update')->andReturn($this->makeIssue(['status' => 'sent']));
        $this->logger->allows('info');

        $this->service->sendIssue(7, 5);
        $this->assertTrue(true);
    }

    public function test_send_issue_overrides_newsletter_content_when_issue_has_blocks(): void
    {
        $issueBlocks = [['type' => 'heading', 'data' => ['text' => 'Issue heading']]];
        $issue = $this->makeIssue([
            'id' => 7,
            'status' => 'draft',
            'newsletter_id' => 1,
            'content_blocks' => $issueBlocks,
        ]);

        $this->expectTransaction();

        $newsletter = new Newsletter();
        $newsletter->id = 1;
        $newsletter->site_id = 5;
        $newsletter->title = 'Test Newsletter';
        $newsletter->content_blocks = [['type' => 'text', 'data' => ['content' => 'Newsletter content']]];
        $newsletter->content_type = 'manual';

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        // The cloned newsletter must carry the issue blocks, not the newsletter's
        $this->sendService->expects('sendNewsletter')
            ->withArgs(function (Newsletter $nl) use ($newsletter, $issueBlocks) {
                return $nl !== $newsletter                    // must be a clone
                    && $nl->content_blocks === $issueBlocks  // issue blocks override
                    && $nl->content_type === 'custom_blocks';
            })
            ->andReturn(['success' => true, 'send_id' => 1, 'recipients' => 10, 'failed' => 0]);

        $this->issueRepository->allows('update')->andReturn($this->makeIssue(['status' => 'sent']));
        $this->logger->allows('info');

        $this->service->sendIssue(7, 5);
        $this->assertTrue(true);
    }

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

    // =========================================================================
    // manualSendIssue()
    // =========================================================================

    public function test_manual_send_all_delegates_to_send_newsletter(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $this->sendService->expects('sendNewsletter')
            ->withArgs(fn($nl, $siteId) => $siteId === 5)
            ->andReturn(['success' => true, 'send_id' => 99, 'recipients' => 50]);

        $this->logger->allows('info');

        $dto = IssueManualSendDTO::fromArray(['send_type' => 'all']);
        $result = $this->service->manualSendIssue(7, 5, $dto);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['queued']);
        $this->assertStringContainsString('all subscribers', $result['message']);
    }

    public function test_manual_send_custom_delegates_to_send_to_custom_emails(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);

        $emails = ['a@test.com', 'b@test.com'];

        $this->sendService->expects('sendToCustomEmails')
            ->withArgs(fn($nl, $addrs, $siteId) => $addrs === $emails && $siteId === 5)
            ->andReturn(['success' => true, 'send_id' => 100, 'recipients' => 2]);

        $this->logger->allows('info');

        $dto = IssueManualSendDTO::fromArray(['send_type' => 'custom', 'custom_emails' => $emails]);
        $result = $this->service->manualSendIssue(7, 5, $dto);

        $this->assertTrue($result['queued']);
        $this->assertStringContainsString('2', $result['message']);
    }

    public function test_manual_send_does_not_transition_issue_status(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft', 'newsletter_id' => 1]);
        $newsletter = $this->makeNewsletter();

        $this->issueRepository->expects('find')->with(7)->andReturn($issue);
        $this->newsletterRepository->expects('find')->with(1)->andReturn($newsletter);
        $this->sendService->allows('sendNewsletter')->andReturn(['success' => true, 'send_id' => 1]);
        $this->logger->allows('info');

        // update() must NOT be called — manual send is out-of-band
        $this->issueRepository->expects('update')->never();

        $dto = IssueManualSendDTO::fromArray(['send_type' => 'all']);
        $this->service->manualSendIssue(7, 5, $dto);
        $this->assertTrue(true);
    }

    public function test_manual_send_throws_when_issue_not_found(): void
    {
        $this->issueRepository->expects('find')->with(99)->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Issue 99 not found');

        $dto = IssueManualSendDTO::fromArray(['send_type' => 'all']);
        $this->service->manualSendIssue(99, 5, $dto);
    }

    public function test_manual_send_throws_when_issue_belongs_to_different_site(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'site_id' => 99]);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectException(\RuntimeException::class);

        $dto = IssueManualSendDTO::fromArray(['send_type' => 'all']);
        $this->service->manualSendIssue(7, 5, $dto);
    }

    // =========================================================================
    // updateIssue()
    // =========================================================================

    public function test_update_issue_persists_changes_for_draft_issue(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft']);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $txCalled = $this->expectTransaction();

        $updatedIssue = $this->makeIssue(['subject' => 'New Subject', 'status' => 'ready']);
        $this->issueRepository->expects('update')
            ->withArgs(fn($id, $data) => $id === 7 && $data['subject'] === 'New Subject')
            ->andReturn($updatedIssue);

        $dto = NewsletterIssueDTO::fromArray(['subject' => 'New Subject', 'status' => 'ready']);
        $result = $this->service->updateIssue(7, 5, $dto);

        $this->assertSame($updatedIssue, $result);
        $this->assertTrue($txCalled());
    }

    public function test_update_issue_throws_domain_exception_for_sent_issue(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'sent']);
        $issue->allows('isSent')->andReturn(true);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectTransaction();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        $dto = NewsletterIssueDTO::fromArray(['subject' => 'Attempt to change sent issue']);
        $this->service->updateIssue(7, 5, $dto);
    }

    public function test_update_issue_throws_runtime_exception_when_not_found(): void
    {
        $this->issueRepository->expects('find')->with(99)->andReturn(null);

        $this->expectTransaction();

        $this->expectException(\RuntimeException::class);

        $dto = NewsletterIssueDTO::fromArray([]);
        $this->service->updateIssue(99, 5, $dto);
    }

    public function test_update_issue_validates_blocks_when_provided(): void
    {
        $issue = $this->makeIssue(['id' => 7, 'status' => 'draft']);
        $this->issueRepository->expects('find')->with(7)->andReturn($issue);

        $this->expectTransaction();

        $blocks = [['type' => 'heading', 'data' => ['text' => 'Hi']]];
        $this->blockPayloadValidator->expects('validate')->with($blocks)->once();
        $this->issueRepository->expects('update')->andReturn($this->makeIssue());

        $dto = NewsletterIssueDTO::fromArray(['content_blocks' => $blocks]);
        $this->service->updateIssue(7, 5, $dto);
        $this->assertTrue(true);
    }

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

        $dto = NewsletterIssueDTO::fromArray(['subject' => 'x']);
        $this->service->updateIssue(7, 5, $dto);

        $this->assertTrue($transactionCalled, 'updateIssue must execute inside a transaction');
    }

    // =========================================================================
    // listIssues()
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

    // =========================================================================
    // Setup / teardown / helpers
    // =========================================================================

    protected function setUp(): void
    {

        $this->issueRepository = Mockery::mock(NewsletterIssueRepository::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->sendService = Mockery::mock(NewsletterSendService::class);
        $this->blockPayloadValidator = Mockery::mock(BlockPayloadValidator::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->events = CapturingEventDispatcher::fake();

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
     * Expect transaction() to be called exactly once.
     * Returns a callable that returns true after the transaction fires — useful
     * for asserting the call happened when the primary assertion is on something else.
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

        return function () use (&$wasCalled) {
            return $wasCalled;
        };

    }

    /**
     * Allow transaction() to be called any number of times without asserting it.
     * Use in tests where the transaction is not the subject under test.
     */
    private function allowTransaction(): void
    {
        $this->databaseMock->allows('transaction')
            ->andReturnUsing(fn(callable $fn) => $fn());
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

    private function makeIssue(array $attributes = []): NewsletterIssue|MockInterface
    {
        $issue = Mockery::mock(NewsletterIssue::class)->makePartial();

        $issue->id = $attributes['id'] ?? 1;
        $issue->site_id = $attributes['site_id'] ?? 5;
        $issue->newsletter_id = $attributes['newsletter_id'] ?? 1;
        $issue->subject = $attributes['subject'] ?? 'Test Issue';
        $issue->status = $attributes['status'] ?? 'draft';
        $issue->content_blocks = $attributes['content_blocks'] ?? null;
        $issue->snapshot_json = $attributes['snapshot_json'] ?? null;
        $issue->send_id = $attributes['send_id'] ?? null;
        $issue->sent_at = $attributes['sent_at'] ?? null;

        // Wire status helpers from real enum logic unless caller sets a mock expectation
        $issue->allows('isSent')->andReturnUsing(
            fn() => $issue->status === NewsletterIssueStatus::Sent->value
        );

        return $issue;
    }
}
