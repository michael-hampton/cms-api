<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationCandidateResolver;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

class ProcessSubscriptionCommunicationsJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public readonly int     $subscriptionId,
        public readonly ?string $date = null,
    ) {
    }

    public SubscriptionRepository                    $subscriptionRepository;
    public SubscriptionCommunicationCandidateResolver $candidateResolver;
    public SubscriptionCommunicationSender           $sender;

    public function handle(): void
    {
        $subscription = $this->subscriptionRepository->find($this->subscriptionId);

        if ($subscription === null) {
            Logger::warning('ProcessSubscriptionCommunicationsJob: subscription not found', [
                'subscription_id' => $this->subscriptionId,
            ]);
            return;
        }

        try {
            $date = $this->date !== null
                ? new DateTimeImmutable($this->date)
                : new DateTimeImmutable('today');
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid date supplied: %s', $this->date),
                previous: $e
            );
        }

        $candidates = $this->candidateResolver->dueForSubscription($subscription, $date);

        if (empty($candidates)) {
            return;
        }

        foreach ($candidates as $candidate) {
            try {
                $this->sender->send(
                    $subscription,
                    $candidate['communication'],
                    $candidate['schedule'],
                );
            } catch (\Throwable $e) {
                // Non-critical batch context: log and continue so one failure
                // doesn't abort the rest of this subscription's communications.
                Logger::error('ProcessSubscriptionCommunicationsJob: send failed', [
                    'subscription_id'  => $this->subscriptionId,
                    'communication_id' => $candidate['communication']->id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }
}