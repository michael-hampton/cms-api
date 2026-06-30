<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Queue\Dispatchable;
use App\Framework\Queue\InteractsWithQueue;
use App\Framework\Queue\Queueable;
use App\Framework\Queue\SerializesModels;
use App\Framework\Queue\ShouldQueue;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Services\Subscriptions\SubscriptionPauseService;

class ResumeScheduledSubscriptionJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    private SubscriptionPauseService $pauseService;
    private SubscriptionDeliveryService $deliveryService;
    private Logger $logger;

    public function __construct(private readonly int $subscriptionId)
    {
    }

    public function handle(): void
    {
        try {
            $this->pauseService->processScheduledResume($this->subscriptionId);
            $this->deliveryService->processScheduledResume($this->subscriptionId);
        } catch (\Throwable $e) {
            $this->logger->error('ResumeScheduledSubscriptionJob failed', [
                'subscription_id' => $this->subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}