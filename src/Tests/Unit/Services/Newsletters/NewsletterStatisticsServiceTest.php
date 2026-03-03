<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Support\Collection;
use App\Models\NewsletterSendRecipient;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendPageViewRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Services\Newsletter\NewsletterStatisticsService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterStatisticsServiceTest extends FunctionalTestCase
{
    // -------------------------------------------------------------------------
    // Fixture builders
    // -------------------------------------------------------------------------

    private function newsletter(int $id, int $siteId, string $title = 'Test Newsletter'): object
    {
        return (object)['id' => $id, 'site_id' => $siteId, 'title' => $title];
    }

    private function send(int $id, int $newsletterId, string $sentAt = '2024-06-01 10:00:00', int $recipients = 100, int $failed = 0): object
    {
        return (object)[
            'id' => $id,
            'newsletter_id' => $newsletterId,
            'sent_at' => new \DateTime($sentAt),
            'recipient_count' => $recipients,
            'failed_count' => $failed,
            'success_count' => $recipients - $failed,
            'pending_count' => 0,
        ];
    }

    private function pageView(int $id, int $sendId, int $pageId, string $email, string $clickedAt = '2024-06-01 12:00:00'): object
    {
        return (object)[
            'id' => $id,
            'newsletter_send_id' => $sendId,
            'page_id' => $pageId,
            'email' => $email,
            'clicked_at' => $clickedAt,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
        ];
    }

    private function recipient(
        int    $id,
        int    $sendId,
        string $email,
        string $status = NewsletterSendRecipient::STATUS_SENT,
        int    $attempts = 1,
        string $updatedAt = '2024-06-01 11:00:00',
        string $errorMessage = ''
    ): object
    {
        return (object)[
            'id' => $id,
            'newsletter_send_id' => $sendId,
            'email' => $email,
            'status' => $status,
            'attempts' => $attempts,
            'updated_at' => $updatedAt,
            'error_message' => $errorMessage ?: null,
        ];
    }

    // -------------------------------------------------------------------------
    // Service factory — mock only the repositories
    // -------------------------------------------------------------------------

    private function makeService(
        array $newsletters = [],
        array $sends = [],
        array $pageViews = [],
        array $recipients = [],
    ): NewsletterStatisticsService
    {
        $newsletterRepo = $this->createMock(NewsletterRepository::class);
        $newsletterRepo->method('findBySite')->willReturn(new Collection($newsletters));

        $sendRepo = $this->createMock(NewsletterSendRepository::class);
        $sendRepo->method('getSendsByNewsletterIds')->willReturn(new Collection($sends));

        $pageViewRepo = $this->createMock(NewsletterSendPageViewRepository::class);
        $pageViewRepo->method('getViewsBySendIds')
            ->willReturnCallback(function (array $sendIds, ?string $dateFrom, ?string $dateTo) use ($pageViews) {
                $filtered = array_filter($pageViews, function ($v) use ($sendIds, $dateFrom, $dateTo) {
                    if (!in_array($v->newsletter_send_id, $sendIds)) {
                        return false;
                    }
                    $date = substr($v->clicked_at, 0, 10);
                    if ($dateFrom && $date < $dateFrom) {
                        return false;
                    }
                    if ($dateTo && $date > $dateTo) {
                        return false;
                    }
                    return true;
                });
                return new Collection(array_values($filtered));
            });

        $recipientRepo = $this->createMock(NewsletterSendRecipientRepository::class);
        $recipientRepo->method('getRecipientsBySendIds')
            ->willReturnCallback(function (array $sendIds, ?string $status, ?string $dateFrom, ?string $dateTo) use ($recipients) {
                $filtered = array_filter($recipients, function ($r) use ($sendIds, $status, $dateFrom, $dateTo) {
                    if (!in_array($r->newsletter_send_id, $sendIds)) {
                        return false;
                    }
                    if ($status !== null && $r->status !== $status) {
                        return false;
                    }
                    $date = substr($r->updated_at, 0, 10);
                    if ($dateFrom && $date < $dateFrom) {
                        return false;
                    }
                    if ($dateTo && $date > $dateTo) {
                        return false;
                    }
                    return true;
                });
                return new Collection(array_values($filtered));
            });

        return new NewsletterStatisticsService($newsletterRepo, $sendRepo, $pageViewRepo, $recipientRepo);
    }

    // =========================================================================
    // getClickDetails
    // =========================================================================

    public function test_getClickDetails_returns_empty_result_when_site_has_no_newsletters(): void
    {
        $result = $this->makeService()->getClickDetails(siteId: 1);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['pagination']['total']);
    }

    public function test_getClickDetails_returns_empty_result_when_newsletters_have_no_sends(): void
    {
        $result = $this->makeService(
            newsletters: [$this->newsletter(1, 1)],
            sends: []
        )->getClickDetails(siteId: 1);

        $this->assertSame([], $result['data']);
    }

    public function test_getClickDetails_assembles_row_fields_correctly(): void
    {
        $nl = $this->newsletter(10, 1, 'Weekly Digest');
        $send = $this->send(20, 10);
        $view = $this->pageView(30, 20, 40, 'alice@example.com', '2024-06-01 12:00:00');

        $result = $this->makeService([$nl], [$send], [$view])->getClickDetails(siteId: 1);

        $this->assertCount(1, $result['data']);
        $row = $result['data'][0];
        $this->assertSame('alice@example.com', $row['email']);
        $this->assertSame(40, $row['page_id']);
        $this->assertSame('Weekly Digest', $row['newsletter_title']);
        $this->assertSame(10, $row['newsletter_id']);
        $this->assertSame('2024-06-01 12:00:00', $row['clicked_at']);
    }

    public function test_getClickDetails_filters_by_email_search(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'alice@example.com'),
                $this->pageView(11, 2, 5, 'bob@example.com'),
            ]
        )->getClickDetails(siteId: 1, search: 'alice');

        $this->assertCount(1, $result['data']);
        $this->assertSame('alice@example.com', $result['data'][0]['email']);
    }

    public function test_getClickDetails_filters_by_date_from(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'early@example.com', '2024-05-31 10:00:00'),
                $this->pageView(11, 2, 5, 'late@example.com', '2024-06-01 10:00:00'),
            ]
        )->getClickDetails(siteId: 1, dateFrom: '2024-06-01');

        $this->assertCount(1, $result['data']);
        $this->assertSame('late@example.com', $result['data'][0]['email']);
    }

    public function test_getClickDetails_filters_by_date_to(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'early@example.com', '2024-05-31 10:00:00'),
                $this->pageView(11, 2, 5, 'late@example.com', '2024-06-15 10:00:00'),
            ]
        )->getClickDetails(siteId: 1, dateTo: '2024-06-01');

        $this->assertCount(1, $result['data']);
        $this->assertSame('early@example.com', $result['data'][0]['email']);
    }

    public function test_getClickDetails_sorts_by_email_ascending(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'zed@example.com'),
                $this->pageView(11, 2, 5, 'alice@example.com'),
            ]
        )->getClickDetails(siteId: 1, sortBy: 'email', sortDirection: 'asc');

        $this->assertSame('alice@example.com', $result['data'][0]['email']);
        $this->assertSame('zed@example.com', $result['data'][1]['email']);
    }

    public function test_getClickDetails_paginates_correctly(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);
        $views = array_map(
            fn($i) => $this->pageView($i, 2, 5, "user{$i}@example.com"),
            range(1, 5)
        );

        $result = $this->makeService([$nl], [$send], $views)->getClickDetails(siteId: 1, page: 2, perPage: 2);

        $this->assertSame(5, $result['pagination']['total']);
        $this->assertSame(2, $result['pagination']['current_page']);
        $this->assertSame(3, $result['pagination']['last_page']);
        $this->assertCount(2, $result['data']);
    }

    // =========================================================================
    // getFailedSendDetails
    // =========================================================================

    public function test_getFailedSendDetails_returns_empty_when_no_sends(): void
    {
        $result = $this->makeService(
            newsletters: [$this->newsletter(1, 1)],
            sends: []
        )->getFailedSendDetails(siteId: 1);

        $this->assertSame([], $result['data']);
    }

    public function test_getFailedSendDetails_only_returns_failed_recipients(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'failed@example.com', NewsletterSendRecipient::STATUS_FAILED, 3, '2024-06-01 10:00:00', 'SMTP timeout'),
                $this->recipient(11, 2, 'ok@example.com', NewsletterSendRecipient::STATUS_SENT),
            ]
        )->getFailedSendDetails(siteId: 1);

        $this->assertCount(1, $result['data']);
        $this->assertSame('failed@example.com', $result['data'][0]['email']);
    }

    public function test_getFailedSendDetails_row_contains_correct_fields(): void
    {
        $nl = $this->newsletter(1, 1, 'My Newsletter');
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [$this->recipient(10, 2, 'x@example.com', NewsletterSendRecipient::STATUS_FAILED, 3, '2024-06-01 10:00:00', 'Connection refused')]
        )->getFailedSendDetails(siteId: 1);

        $row = $result['data'][0];
        $this->assertSame('x@example.com', $row['email']);
        $this->assertSame('Connection refused', $row['error_message']);
        $this->assertSame(3, $row['attempts']);
        $this->assertSame('My Newsletter', $row['newsletter_title']);
        $this->assertSame(1, $row['newsletter_id']);
    }

    public function test_getFailedSendDetails_filters_by_search_on_error_message(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'a@example.com', NewsletterSendRecipient::STATUS_FAILED, 1, '2024-06-01', 'SMTP timeout'),
                $this->recipient(11, 2, 'b@example.com', NewsletterSendRecipient::STATUS_FAILED, 1, '2024-06-01', 'Invalid address'),
            ]
        )->getFailedSendDetails(siteId: 1, search: 'timeout');

        $this->assertCount(1, $result['data']);
        $this->assertSame('a@example.com', $result['data'][0]['email']);
    }

    public function test_getFailedSendDetails_filters_by_date_range(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'old@example.com', NewsletterSendRecipient::STATUS_FAILED, 1, '2024-04-01 10:00:00'),
                $this->recipient(11, 2, 'recent@example.com', NewsletterSendRecipient::STATUS_FAILED, 1, '2024-06-01 10:00:00'),
            ]
        )->getFailedSendDetails(siteId: 1, dateFrom: '2024-05-01');

        $this->assertCount(1, $result['data']);
        $this->assertSame('recent@example.com', $result['data'][0]['email']);
    }

    public function test_getFailedSendDetails_sorts_by_attempts_descending(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'one@example.com', NewsletterSendRecipient::STATUS_FAILED, attempts: 1),
                $this->recipient(11, 2, 'five@example.com', NewsletterSendRecipient::STATUS_FAILED, attempts: 5),
            ]
        )->getFailedSendDetails(siteId: 1, sortBy: 'attempts', sortDirection: 'desc');

        $this->assertSame('five@example.com', $result['data'][0]['email']);
        $this->assertSame('one@example.com', $result['data'][1]['email']);
    }

    // =========================================================================
    // getUniqueClickerDetails
    // =========================================================================

    public function test_getUniqueClickerDetails_groups_multiple_views_by_email(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'alice@example.com', '2024-06-01 10:00:00'),
                $this->pageView(11, 2, 5, 'alice@example.com', '2024-06-02 10:00:00'),
                $this->pageView(12, 2, 5, 'bob@example.com', '2024-06-01 10:00:00'),
            ]
        )->getUniqueClickerDetails(siteId: 1, sortBy: 'click_count', sortDirection: 'desc');

        $this->assertCount(2, $result['data']);
        $this->assertSame('alice@example.com', $result['data'][0]['email']);
        $this->assertSame(2, $result['data'][0]['click_count']);
        $this->assertSame(1, $result['data'][1]['click_count']);
    }

    public function test_getUniqueClickerDetails_last_clicked_at_is_the_maximum_date(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'alice@example.com', '2024-06-01 08:00:00'),
                $this->pageView(11, 2, 5, 'alice@example.com', '2024-06-03 20:00:00'),
            ]
        )->getUniqueClickerDetails(siteId: 1);

        $this->assertSame('2024-06-03 20:00:00', $result['data'][0]['last_clicked_at']);
    }

    public function test_getUniqueClickerDetails_counts_distinct_pages_per_email(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'alice@example.com', '2024-06-01 10:00:00'),
                $this->pageView(11, 2, 5, 'alice@example.com', '2024-06-02 10:00:00'), // same page
                $this->pageView(12, 2, 99, 'alice@example.com', '2024-06-03 10:00:00'), // different page
            ]
        )->getUniqueClickerDetails(siteId: 1);

        $this->assertSame(3, $result['data'][0]['click_count']);
        $this->assertSame(2, $result['data'][0]['unique_pages_clicked']);
    }

    public function test_getUniqueClickerDetails_filters_by_email_search(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send],
            [
                $this->pageView(10, 2, 5, 'alice@example.com'),
                $this->pageView(11, 2, 5, 'bob@example.com'),
            ]
        )->getUniqueClickerDetails(siteId: 1, search: 'alice');

        $this->assertCount(1, $result['data']);
        $this->assertSame('alice@example.com', $result['data'][0]['email']);
    }

    public function test_getUniqueClickerDetails_paginates(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);
        $views = array_map(
            fn($i) => $this->pageView($i, 2, 5, "user{$i}@example.com"),
            range(1, 6)
        );

        $result = $this->makeService([$nl], [$send], $views)->getUniqueClickerDetails(siteId: 1, page: 1, perPage: 4);

        $this->assertSame(6, $result['pagination']['total']);
        $this->assertSame(2, $result['pagination']['last_page']);
        $this->assertCount(4, $result['data']);
    }

    // =========================================================================
    // getSendDetails
    // =========================================================================

    public function test_getSendDetails_assembles_row_fields_correctly(): void
    {
        $nl = $this->newsletter(1, 1, 'The Dispatch');
        $send = $this->send(2, 1, '2024-06-15 09:00:00', 200, 5);

        $result = $this->makeService([$nl], [$send])->getSendDetails(siteId: 1);

        $this->assertCount(1, $result['data']);
        $row = $result['data'][0];
        $this->assertSame(2, $row['send_id']);
        $this->assertSame('The Dispatch', $row['newsletter_title']);
        $this->assertSame(200, $row['total_recipients']);
        $this->assertSame(5, $row['failed_count']);
        $this->assertSame(195, $row['success_count']);
    }

    public function test_getSendDetails_filters_by_date_from(): void
    {
        $nl = $this->newsletter(1, 1);

        $result = $this->makeService(
            [$nl],
            [
                $this->send(2, 1, '2024-05-01 10:00:00'),
                $this->send(3, 1, '2024-06-01 10:00:00'),
            ]
        )->getSendDetails(siteId: 1, dateFrom: '2024-06-01');

        $this->assertCount(1, $result['data']);
        $this->assertSame(3, $result['data'][0]['send_id']);
    }

    public function test_getSendDetails_filters_by_date_to(): void
    {
        $nl = $this->newsletter(1, 1);

        $result = $this->makeService(
            [$nl],
            [
                $this->send(2, 1, '2024-05-01 10:00:00'),
                $this->send(3, 1, '2024-06-15 10:00:00'),
            ]
        )->getSendDetails(siteId: 1, dateTo: '2024-06-01');

        $this->assertCount(1, $result['data']);
        $this->assertSame(2, $result['data'][0]['send_id']);
    }

    public function test_getSendDetails_filters_by_newsletter_title_search(): void
    {
        $result = $this->makeService(
            [
                $this->newsletter(1, 1, 'Tech Weekly'),
                $this->newsletter(2, 1, 'Sports Monthly'),
            ],
            [
                $this->send(10, 1),
                $this->send(11, 2),
            ]
        )->getSendDetails(siteId: 1, search: 'tech');

        $this->assertCount(1, $result['data']);
        $this->assertSame('Tech Weekly', $result['data'][0]['newsletter_title']);
    }

    public function test_getSendDetails_sorts_by_sent_at_ascending(): void
    {
        $nl = $this->newsletter(1, 1);

        $result = $this->makeService(
            [$nl],
            [
                $this->send(2, 1, '2024-06-10 10:00:00'),
                $this->send(3, 1, '2024-06-01 10:00:00'),
            ]
        )->getSendDetails(siteId: 1, sortBy: 'sent_at', sortDirection: 'asc');

        $this->assertSame(3, $result['data'][0]['send_id']);
        $this->assertSame(2, $result['data'][1]['send_id']);
    }

    public function test_getSendDetails_paginates_correctly(): void
    {
        $nl = $this->newsletter(1, 1);
        $sends = array_map(fn($i) => $this->send($i, 1, "2024-0{$i}-01 10:00:00"), range(1, 7));

        $result = $this->makeService([$nl], $sends)->getSendDetails(siteId: 1, page: 2, perPage: 3);

        $this->assertSame(7, $result['pagination']['total']);
        $this->assertSame(3, $result['pagination']['last_page']);
        $this->assertCount(3, $result['data']);
    }

    // =========================================================================
    // getRecipientDetails
    // =========================================================================

    public function test_getRecipientDetails_assembles_row_fields_correctly(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [$this->recipient(10, 2, 'jane@example.com', NewsletterSendRecipient::STATUS_SENT, 1, '2024-06-05 15:00:00')]
        )->getRecipientDetails(siteId: 1);

        $this->assertCount(1, $result['data']);
        $row = $result['data'][0];
        $this->assertSame('jane@example.com', $row['email']);
        $this->assertSame(NewsletterSendRecipient::STATUS_SENT, $row['status']);
        $this->assertSame(1, $row['attempts']);
        $this->assertSame('2024-06-05 15:00:00', $row['last_attempt_at']);
    }

    public function test_getRecipientDetails_filters_by_email_search(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'alice@example.com'),
                $this->recipient(11, 2, 'bob@example.com'),
            ]
        )->getRecipientDetails(siteId: 1, search: 'bob');

        $this->assertCount(1, $result['data']);
        $this->assertSame('bob@example.com', $result['data'][0]['email']);
    }

    public function test_getRecipientDetails_filters_by_error_message_search(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'a@example.com', NewsletterSendRecipient::STATUS_FAILED, 1, '2024-06-01', 'Connection timed out'),
                $this->recipient(11, 2, 'b@example.com', NewsletterSendRecipient::STATUS_FAILED, 1, '2024-06-01', 'Invalid address'),
            ]
        )->getRecipientDetails(siteId: 1, search: 'timed out');

        $this->assertCount(1, $result['data']);
        $this->assertSame('a@example.com', $result['data'][0]['email']);
    }

    public function test_getRecipientDetails_filters_by_date_range(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'old@example.com', updatedAt: '2024-04-01 10:00:00'),
                $this->recipient(11, 2, 'recent@example.com', updatedAt: '2024-06-01 10:00:00'),
            ]
        )->getRecipientDetails(siteId: 1, dateFrom: '2024-05-01');

        $this->assertCount(1, $result['data']);
        $this->assertSame('recent@example.com', $result['data'][0]['email']);
    }

    public function test_getRecipientDetails_sorts_by_attempts_ascending(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);

        $result = $this->makeService(
            [$nl], [$send], [],
            [
                $this->recipient(10, 2, 'many@example.com', attempts: 5),
                $this->recipient(11, 2, 'few@example.com', attempts: 1),
            ]
        )->getRecipientDetails(siteId: 1, sortBy: 'attempts', sortDirection: 'asc');

        $this->assertSame('few@example.com', $result['data'][0]['email']);
        $this->assertSame('many@example.com', $result['data'][1]['email']);
    }

    public function test_getRecipientDetails_paginates_correctly(): void
    {
        $nl = $this->newsletter(1, 1);
        $send = $this->send(2, 1);
        $recipients = array_map(fn($i) => $this->recipient($i, 2, "user{$i}@example.com"), range(1, 10));

        $result = $this->makeService([$nl], [$send], [], $recipients)
            ->getRecipientDetails(siteId: 1, page: 3, perPage: 3);

        $this->assertSame(10, $result['pagination']['total']);
        $this->assertSame(4, $result['pagination']['last_page']);
        $this->assertCount(3, $result['data']);
    }

    // =========================================================================
    // Pagination shape (shared behaviour, tested via getSendDetails)
    // =========================================================================

    public function test_pagination_from_and_to_are_correct_mid_page(): void
    {
        $nl = $this->newsletter(1, 1);
        $sends = array_map(fn($i) => $this->send($i, 1), range(1, 10));

        $result = $this->makeService([$nl], $sends)->getSendDetails(siteId: 1, page: 2, perPage: 3);

        $this->assertSame(4, $result['pagination']['from']);
        $this->assertSame(6, $result['pagination']['to']);
    }

    public function test_pagination_to_is_clamped_on_final_partial_page(): void
    {
        $nl = $this->newsletter(1, 1);
        $sends = array_map(fn($i) => $this->send($i, 1), range(1, 5));

        $result = $this->makeService([$nl], $sends)->getSendDetails(siteId: 1, page: 2, perPage: 3);

        $this->assertSame(4, $result['pagination']['from']);
        $this->assertSame(5, $result['pagination']['to']);
        $this->assertCount(2, $result['data']);
    }

    public function test_pagination_last_page_is_correct_on_exact_multiple(): void
    {
        $nl = $this->newsletter(1, 1);
        $sends = array_map(fn($i) => $this->send($i, 1), range(1, 6));

        $result = $this->makeService([$nl], $sends)->getSendDetails(siteId: 1, page: 1, perPage: 3);

        $this->assertSame(2, $result['pagination']['last_page']);
    }
}