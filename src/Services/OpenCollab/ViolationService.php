<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ViolationAction;
use App\Enums\OpenCollab\ViolationSeverity;
use App\Enums\OpenCollab\ViolationType;
use App\Events\OpenCollab\ViolationRecordedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\ContributorViolation;
use App\Repositories\OpenCollab\ViolationRepository;
use App\Services\OpenCollab\Notifications\ArticleRejectedNotification;
use App\Services\OpenCollab\Notifications\ViolationRecordedNotification;
use App\Services\User\UserLifecycleServiceInterface;

/**
 * Records violations and enforces automatic suspension/ban thresholds.
 *
 * Threshold rules (unresolved violations within rolling count):
 *   High severity  : 1  → automatic ban
 *   Medium severity: 3  → automatic suspension
 *   Low severity   : 5  → automatic suspension
 *
 * Admins may override action_taken when recording. If they do not,
 * the service computes the appropriate action automatically.
 *
 * Account suspension sets is_active = false on the User record.
 * Bans also set is_active = false (permanent until admin resolves).
 */
class ViolationService
{
    public function __construct(
        private readonly ViolationRepository     $violationRepository,
        private readonly UserLifecycleServiceInterface $userLifecycle,
        private readonly EventDispatcher         $eventDispatcher,
        private readonly Database                $database,
        private readonly Logger                  $logger,
        private readonly NotificationDispatcher $notificationDispatcher,

    )
    {
    }

    /**
     * Record a violation against a contributor.
     *
     * If $actionOverride is null, the service picks the appropriate action
     * based on threshold counts. If provided, the admin's choice is respected.
     *
     * @throws \InvalidArgumentException if the contributor user is not found
     */
    public function record(
        int               $userId,
        int               $siteId,
        ViolationType     $type,
        ViolationSeverity $severity,
        string            $reason,
        int               $adminId,
        ?ViolationAction  $actionOverride = null,
        ?int              $pageId = null,
    ): ContributorViolation
    {
        $user = $this->userLifecycle->findById($userId);

        if (!$user) {
            throw new \InvalidArgumentException("User [{$userId}] not found.");
        }

        $action = $actionOverride ?? $this->computeAction($userId, $siteId, $severity);

        $violation = $this->database->transaction(function () use (
            $userId, $siteId, $type, $severity, $reason, $adminId, $action, $pageId, $user
        ): ContributorViolation {
            $violation = $this->violationRepository->create([
                'user_id' => $userId,
                'site_id' => $siteId,
                'type' => $type->value,
                'severity' => $severity->value,
                'reason' => $reason,
                'action_taken' => $action->value,
                'created_by' => $adminId,
                'page_id' => $pageId,
            ]);

            // Suspend or ban account immediately when the action requires it.
            if (in_array($action, [ViolationAction::Suspension, ViolationAction::Ban], true)) {
                $this->userLifecycle->deactivateContributor($userId, $adminId, $reason);

                $this->logger->info('Contributor account deactivated due to violation.', [
                    'user_id' => $userId,
                    'violation_id' => $violation->id,
                    'action' => $action->value,
                ]);
            }

            if ($user) {
                $this->notificationDispatcher->dispatch(
                    new ViolationRecordedNotification($violation, $user)
                );
            }

            return $violation;
        });

        $this->eventDispatcher->dispatch(new ViolationRecordedEvent($violation, $userId));

        return $violation;
    }

    /**
     * Determine the appropriate action based on current unresolved violation counts.
     * Counts are evaluated BEFORE the new violation is saved.
     */
    private function computeAction(int $userId, int $siteId, ViolationSeverity $severity): ViolationAction
    {
        // After adding this violation, would we hit the threshold?
        $currentCount = $this->violationRepository->unresolvedCountBySeverity($userId, $siteId, $severity);
        $countAfter = $currentCount + 1;

        return match (true) {
            $severity === ViolationSeverity::High && $countAfter >= 1 => ViolationAction::Ban,
            $severity === ViolationSeverity::Medium && $countAfter >= 3 => ViolationAction::Suspension,
            $severity === ViolationSeverity::Low && $countAfter >= 5 => ViolationAction::Suspension,
            default => ViolationAction::Warning,
        };
    }

    /**
     * Resolve a violation — removes the block if no other active bans/suspensions remain.
     *
     * @throws \InvalidArgumentException if the violation is not found
     */
    public function resolve(int $violationId, int $adminId, ?string $notes = null): ContributorViolation
    {
        $violation = $this->violationRepository->find($violationId);

        if (!$violation) {
            throw new \InvalidArgumentException("Violation [{$violationId}] not found.");
        }

        if ($violation->isResolved()) {
            throw new \InvalidArgumentException("Violation [{$violationId}] is already resolved.");
        }

        return $this->database->transaction(function () use ($violation, $adminId, $notes): ContributorViolation {
            $this->violationRepository->update($violation->id, [
                'resolved_at' => date('Y-m-d H:i:s'),
                'resolved_by' => $adminId,
                'resolution_notes' => $notes,
            ]);

            // Re-activate account if no other active bans or suspensions remain.
            $hasActiveBan = $this->violationRepository->hasActiveBan(
                $violation->user_id,
                $violation->site_id
            );
            $hasActiveSuspension = $this->violationRepository->hasActiveSuspension(
                $violation->user_id,
                $violation->site_id
            );

            if (!$hasActiveBan && !$hasActiveSuspension) {
                $this->userLifecycle->reactivateContributor($violation->user_id, $adminId, $notes);
            }

            return $this->violationRepository->find($violation->id);
        });
    }

    // -------------------------------------------------------------------------

    /**
     * Whether a contributor is currently blocked from publishing.
     */
    public function isBlocked(int $userId, int $siteId): bool
    {
        return $this->violationRepository->hasActiveBan($userId, $siteId)
            || $this->violationRepository->hasActiveSuspension($userId, $siteId);
    }
}
