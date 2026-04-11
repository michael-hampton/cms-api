<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ViolationAction;
use App\Enums\OpenCollab\ViolationSeverity;
use App\Enums\OpenCollab\ViolationType;
use App\Events\OpenCollab\ViolationRecordedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\ContributorViolation;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ViolationRepository;

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
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventDispatcher         $eventDispatcher,
        private readonly Database                $database,
        private readonly Logger                  $logger,
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
        $user = $this->userRepository->find($userId);

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
                $this->userRepository->update($userId, ['is_active' => false]);

                $this->logger->info('Contributor account deactivated due to violation.', [
                    'user_id' => $userId,
                    'violation_id' => $violation->id,
                    'action' => $action->value,
                ]);
            }

            return $violation;
        });

        $this->eventDispatcher->dispatch(new ViolationRecordedEvent($violation));

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
                $this->userRepository->update($violation->user_id, ['is_active' => true]);
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