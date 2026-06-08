<?php

namespace App\Listeners\Cms;

use App\Events\Cms\ContentApproved;
use App\Events\Cms\ContentEditoriallyModified;
use App\Events\Cms\ContentHeld;
use App\Events\Cms\ContentRejected;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Models\User;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\UserNotificationRepository;
use App\Services\OpenCollab\SitePermissionResolver;

class SendContentWorkflowNotification
{
    public function __construct(
        private readonly UserNotificationRepository $notifications,
        private readonly RbacRepository $rbacRepository,
        private readonly SitePermissionResolver $permissionResolver,
    ) {
    }

    public function handle(object $event): void
    {
        if ($event instanceof ContentSubmittedForApproval) {
            $this->notifyReviewers($event);
            return;
        }

        if ($event instanceof ContentApproved) {
            $this->notifyOwner($event, $this->notificationType($event->contentType, 'approved'));
            return;
        }

        if ($event instanceof ContentRejected) {
            $this->notifyOwner($event, $this->notificationType($event->contentType, 'rejected'), $event->reason);
            return;
        }

        if ($event instanceof ContentHeld) {
            $this->notifyOwner($event, $this->notificationType($event->contentType, 'held'), $event->reason);
        }

        if ($event instanceof ContentEditoriallyModified) {
            $this->notifyOwner($event, $this->notificationType($event->contentType, 'editorially_modified'));
            return;
        }
    }

    private function notifyReviewers(ContentSubmittedForApproval $event): void
    {
        $permission = "{$event->contentType}.review";

        foreach ($this->rbacRepository->usersForSite($event->siteId) as $user) {
            $userId = (int) ($user['id'] ?? 0);

            if (!$userId || (isset($user['is_active']) && !(bool) $user['is_active'])) {
                continue;
            }

            if (
                !$this->permissionResolver->allows($userId, $event->siteId, $permission)
                && !$this->permissionResolver->allows($userId, $event->siteId, 'content.review')
            ) {
                continue;
            }

            $this->notifications->create(
                $userId,
                $this->notificationType($event->contentType, 'submitted_for_approval'),
                $this->payload($event, $event->actorId, $this->notificationType($event->contentType, 'submitted_for_approval'))
            );
        }
    }

    private function notifyOwner(object $event, string $type, ?string $reason = null): void
    {
        if (!$event->ownerId || $event->ownerId === $event->actorId) {
            return;
        }

        $user = User::find($event->ownerId);
        if ($user && isset($user->is_active) && !(bool) $user->is_active) {
            return;
        }

        $payload = $this->payload($event, $event->actorId, $type);
        if ($reason !== null && $reason !== '') {
            $payload['reason'] = $reason;
        }

        $this->notifications->create($event->ownerId, $type, $payload);
    }

    private function payload(object $event, int $actionUserId, string $notificationType): array
    {
        $singular = rtrim($event->contentType, 's');

        $payload = [
            "{$singular}_id" => $event->contentId,
            "{$singular}_title" => $event->title,
            'site_id' => (int) $event->siteId,
            'content_type' => $singular,
            'content_id' => $event->contentId,
            'content_title' => $event->title,
            'notification_type' => $notificationType,
            'action_user_id' => $actionUserId,
            'url' => $this->urlFor($event->contentType, $event->contentId),
        ];

        if (isset($event->historyId)) {
            $payload['history_id'] = (int) $event->historyId;
        }

        return $payload;
    }

    private function urlFor(string $contentType, int $contentId): string
    {
        return match ($contentType) {
            'pages' => "/admin/pages/{$contentId}/edit",
            'briefs' => "/admin/briefs/{$contentId}/review",
            default => "/admin/{$contentType}/{$contentId}",
        };
    }

    private function notificationType(string $contentType, string $suffix): string
    {
        return rtrim($contentType, 's') . "_{$suffix}";
    }
}
