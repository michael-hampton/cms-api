<?php

namespace App\Repositories\Newsletters;

use App\Models\NewsletterSendRecipient;
use App\Repositories\Repository;

class NewsletterSendRecipientRepository extends Repository
{
    public function __construct(private readonly NewsletterSendRepository $newsletterSendRepository)
    {
        parent::__construct();
    }

    public function createRecipients(int $sendId, array $emails): array
    {
        $recipients = [];

        foreach ($emails as $email) {
            $recipients[] = $this->create([
                'newsletter_send_id' => $sendId,
                'email' => $email,
                'status' => NewsletterSendRecipient::STATUS_PENDING,
                'attempts' => 0
            ]);
        }

        return $recipients;
    }

    public function getPendingRecipients(int $sendId): array
    {
        return NewsletterSendRecipient::pending()
            ->where('newsletter_send_id', $sendId)
            ->get()
            ->toArray();
    }

    public function getFailedRecipients(int $sendId): array
    {
        return NewsletterSendRecipient::failed()
            ->where('newsletter_send_id', $sendId)
            ->get()
            ->toArray();
    }

    public function getRetryableRecipients(int $sendId, int $maxAttempts = 3): array
    {
        return NewsletterSendRecipient::failed()
            ->where('newsletter_send_id', $sendId)
            ->where('attempts', '<', $maxAttempts)
            ->get()
            ->toArray();
    }

    public function updateSendCounts(int $sendId): void
    {
        $stats = $this->getStatistics($sendId);

        $this->newsletterSendRepository->update($sendId, [
            'sent_count' => $stats['sent'],
            'failed_count' => $stats['failed'],
            'pending_count' => $stats['pending']
        ]);
    }

    public function getStatistics(int $sendId): array
    {
        $stats = NewsletterSendRecipient::query()
            ->where('newsletter_send_id', $sendId)
            ->selectRaw('
        status,
        COUNT(*) as count,
        AVG(attempts) as avg_attempts
    ')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();


        return [
            'total' => array_sum(array_column($stats, 'count')),
            'sent' => $stats[NewsletterSendRecipient::STATUS_SENT]['count'] ?? 0,
            'failed' => $stats[NewsletterSendRecipient::STATUS_FAILED]['count'] ?? 0,
            'pending' => $stats[NewsletterSendRecipient::STATUS_PENDING]['count'] ?? 0,
            'bounced' => $stats[NewsletterSendRecipient::STATUS_BOUNCED]['count'] ?? 0,
            'avg_attempts' => $stats[NewsletterSendRecipient::STATUS_SENT]['avg_attempts'] ?? 0,
        ];
    }

    protected function getModelClass(): string
    {
        return NewsletterSendRecipient::class;
    }
}