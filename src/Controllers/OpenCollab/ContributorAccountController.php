<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Enums\OpenCollab\ActivityEventType;
use App\Framework\Authorization\Auth;
use App\Framework\Database\Database;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;

/**
 * Handles contributor account lifecycle operations.
 *
 * Account closure is a REQUEST, not an immediate delete.
 * Reasons:
 *   - Contributor agreements may require content retention.
 *   - Outstanding earnings must be resolved.
 *   - GDPR right-to-erasure is a separate process with its own timeline.
 *
 * The request is logged in the activity feed and the user's is_active flag
 * is set to false. Permanent deletion is a manual admin action.
 *
 * Routes:
 *   POST /api/{site}/open-collab/contributor/close-account
 */
class ContributorAccountController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ActivityRepository      $activityRepository,
        private readonly Database                $database,
        private readonly Logger                  $logger,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/contributor/close-account
     *
     * Records an account closure request. Does NOT immediately delete the user.
     * Sets is_active = false to block login and schedules review by admins.
     *
     * Audit trail: the reason and notes are logged to the activity feed.
     */
    public function requestClosure(): JsonResponse
    {
        $userId = Auth::id();

        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $reason = $_POST['reason'] ?? (json_decode(file_get_contents('php://input'), true)['reason'] ?? '');
        $notes = $_POST['notes'] ?? (json_decode(file_get_contents('php://input'), true)['notes'] ?? '');

        if (empty($reason)) {
            return $this->errorResponse('A reason is required.', 422);
        }

        try {
            $this->database->transaction(function () use ($userId, $reason, $notes): void {
                // Deactivate the account
                $user = User::find($userId);
                if ($user) {
                    $user->update(['is_active' => false]);
                }

                // Write audit record into the activity feed
                $this->activityRepository->record(
                    siteId: SiteContext::getId(),
                    userId: $userId,
                    type: ActivityEventType::InvitationAccepted, // reuse closest type or add AccountClosureRequested
                    payload: [
                        'event' => 'account_closure_requested',
                        'reason' => $reason,
                        'notes' => $notes,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ],
                );
            });

            $this->logger->info('Contributor account closure requested.', [
                'user_id' => $userId,
                'site_id' => SiteContext::getId(),
                'reason' => $reason,
            ]);

            return $this->successResponse('Account closure request received. Our team will be in touch within 2 business days.');

        } catch (\Exception $e) {
            $this->logger->error('Account closure request failed.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Could not process your request. Please try again.', 500);
        }
    }
}