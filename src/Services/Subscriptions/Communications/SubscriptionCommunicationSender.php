<?php

namespace App\Services\Subscriptions\Communications;

use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Members\CommunicationLogRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Services\MemberInsights\InAppNotificationDispatcher;

/**
 * Sends one communication through each configured channel and records delivery.
 */
class SubscriptionCommunicationSender
{
    public function __construct(
        private readonly SubscriptionCommunicationDeliveryRepository $deliveryRepository,
        private readonly NotificationDispatcher                      $notificationDispatcher,
        private readonly InAppNotificationDispatcher                 $inAppDispatcher,
        private readonly CommunicationLogRepository                  $communicationLogRepository,
        private readonly Logger                                      $logger,
    ) {
    }

    public function send(
        Subscription                       $subscription,
        SubscriptionCommunication          $communication,
        ?SubscriptionCommunicationSchedule $schedule = null,
        array                              $metadata = [],
        ?string                            $dedupeKey = null,
    ): void {
        $channels = $communication->channels ?? [];

        foreach ($channels as $channel) {
            $this->sendViaChannel(
                subscription: $subscription,
                communication: $communication,
                schedule: $schedule,
                channel: $channel,
                metadata: $metadata,
                dedupeKey: $dedupeKey,
            );
        }
    }

    private function sendViaChannel(
        Subscription                       $subscription,
        SubscriptionCommunication          $communication,
        ?SubscriptionCommunicationSchedule $schedule,
        string                             $channel,
        array                              $metadata = [],
        ?string                            $dedupeKey = null,
    ): void {
        if ($this->deliveryRepository->hasAlreadySent(
            $subscription->id,
            $communication->id,
            $schedule?->id,
            $dedupeKey,
        )) {
            $this->logger->info('SubscriptionCommunicationSender: skipping duplicate', [
                'subscription_id' => $subscription->id,
                'communication_id' => $communication->id,
                'schedule_id' => $schedule?->id,
                'channel' => $channel,
                'dedupe_key' => $dedupeKey,
            ]);
            return;
        }

        match ($channel) {
            'email'  => $this->sendEmail($subscription, $communication, $schedule, $metadata, $dedupeKey),
            'in_app' => $this->sendInApp($subscription, $communication, $schedule, $metadata, $dedupeKey),
            default  => $this->logger->warning('SubscriptionCommunicationSender: unknown channel', [
                'channel' => $channel,
            ]),
        };
    }

    private function sendEmail(
        Subscription                       $subscription,
        SubscriptionCommunication          $communication,
        ?SubscriptionCommunicationSchedule $schedule,
        array                              $metadata = [],
        ?string                            $dedupeKey = null,
    ): void {
        $member = $subscription->member;

        if ($member === null) {
            $this->logger->warning('SubscriptionCommunicationSender: no member on subscription', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $delivery = $this->deliveryRepository->recordPending(
            subscriptionId:  $subscription->id,
            memberId:        $member->id,
            communicationId: $communication->id,
            scheduleId:      $schedule?->id,
            channel:         'email',
            recipientEmail:  $member->email,
            metadata:        $metadata,
            dedupeKey:       $dedupeKey,
        );

        $template = $communication->template;

        if (!class_exists($template)) {
            $this->deliveryRepository->markFailed($delivery->id, "Mailable [{$template}] not found.");
            $this->logger->error('SubscriptionCommunicationSender: mailable not found', [
                'template' => $template,
                'delivery_id' => $delivery->id,
            ]);
            return;
        }

        try {
            $mailable = $this->makeMailable(
                template: $template,
                member: $member,
                subscription: $subscription,
                communication: $communication,
                schedule: $schedule,
                metadata: $metadata,
            );

            $mailable->deliveryToken = $delivery->token;

            $notification = new SubscriptionCommunicationNotification(
                mailable: $mailable,
                recipientEmail: $member->email,
                recipientUserId: $member->id,
            );

            $result = $this->notificationDispatcher->dispatch($notification);

            if ($result > 0) {
                $this->deliveryRepository->markSent($delivery->id);
                $this->recordCommunicationLog(
                    memberId: (int) $member->id,
                    communication: $communication,
                    channel: 'email',
                    subject: $mailable->subject ?: ($communication->name ?? null),
                    status: 'sent',
                    templateName: $communication->template ?? null,
                    campaignName: $communication->name ?? null,
                );
                $this->logger->info('SubscriptionCommunicationSender: email sent', [
                    'delivery_id' => $delivery->id,
                ]);
            } else {
                $this->deliveryRepository->markFailed($delivery->id, 'Dispatcher returned zero successes.');
            }
        } catch (\Throwable $e) {
            $this->deliveryRepository->markFailed($delivery->id, $e->getMessage());
            $this->logger->error('SubscriptionCommunicationSender: email dispatch failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendInApp(
        Subscription                       $subscription,
        SubscriptionCommunication          $communication,
        ?SubscriptionCommunicationSchedule $schedule,
        array                              $metadata = [],
        ?string                            $dedupeKey = null,
    ): void {
        $member = $subscription->member;

        if ($member === null) {
            return;
        }

        $delivery = $this->deliveryRepository->recordPending(
            subscriptionId:  $subscription->id,
            memberId:        $member->id,
            communicationId: $communication->id,
            scheduleId:      $schedule?->id,
            channel:         'in_app',
            metadata:        $metadata,
            dedupeKey:       $dedupeKey,
        );

        try {
            $dispatched = $this->inAppDispatcher->dispatchForSubscriptionCommunication(
                $member,
                $communication
            );

            if ($dispatched) {
                $this->deliveryRepository->markSent($delivery->id);
                $this->recordCommunicationLog(
                    memberId: (int) $member->id,
                    communication: $communication,
                    channel: 'in_app',
                    subject: $communication->name ?? null,
                    status: 'sent',
                    templateName: $communication->template ?? null,
                    campaignName: $communication->name ?? null,
                );
            } else {
                $this->deliveryRepository->markFailed($delivery->id, 'In-app dispatch returned false.');
            }
        } catch (\Throwable $e) {
            $this->deliveryRepository->markFailed($delivery->id, $e->getMessage());
            $this->logger->error('SubscriptionCommunicationSender: in-app dispatch failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function makeMailable(
        string                             $template,
        mixed                              $member,
        Subscription                       $subscription,
        SubscriptionCommunication          $communication,
        ?SubscriptionCommunicationSchedule $schedule,
        array                              $metadata = [],
    ): object {
        $reflection = new \ReflectionClass($template);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfParameters() >= 5) {
            return new $template(
                $member,
                $subscription,
                $communication,
                $schedule,
                $metadata,
            );
        }

        return new $template(
            $member,
            $subscription,
            $communication,
            $schedule,
        );
    }

    private function recordCommunicationLog(
        int $memberId,
        SubscriptionCommunication $communication,
        string $channel,
        ?string $subject,
        string $status,
        ?string $templateName,
        ?string $campaignName,
    ): void {
        try {
            $this->communicationLogRepository->record([
                'member_id'     => $memberId,
                'type'          => 'transactional',
                'channel'       => $channel,
                'subject'       => $subject,
                'status'        => $status,
                'template_name' => $templateName,
                'campaign_name' => $campaignName,
                'sent_at'       => now_datetime(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('SubscriptionCommunicationSender: communication log write failed', [
                'member_id' => $memberId,
                'communication_id' => $communication->id ?? null,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
