<?php

namespace App\Services\Cms;

use App\Enums\OpenCollab\ModerationPermission;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

class ContentWorkflowAuthorizationService
{
    public function __construct(
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
    }

    public function assertCanRequestApproval(int $userId, int $siteId, string $contentType = 'content'): void
    {
        $this->assertAny($userId, $siteId, [
            "{$contentType}.submit_for_approval",
            'content.submit',
        ], "Missing permission: {$contentType}.submit_for_approval");
    }

    public function assertCanReview(int $userId, int $siteId, string $contentType = 'content'): void
    {
        $this->assertAny($userId, $siteId, [
            "{$contentType}.review",
            'content.review',
        ], "Missing permission: {$contentType}.review");
    }

    public function assertCanApprove(int $userId, int $siteId, string $contentType = 'content'): void
    {
        $this->assertAny($userId, $siteId, [
            "{$contentType}.approve",
            'content.approve',
        ], "Missing permission: {$contentType}.approve");
    }

    public function assertCanReject(int $userId, int $siteId, string $contentType = 'content'): void
    {
        $this->assertAny($userId, $siteId, [
            "{$contentType}.reject",
            'content.reject',
        ], "Missing permission: {$contentType}.reject");
    }

    public function assertCanHold(int $userId, int $siteId, string $contentType = 'content'): void
    {
        $this->assertAny($userId, $siteId, [
            "{$contentType}.hold",
        ], "Missing permission: {$contentType}.hold");
    }

    /**
     * Passes if the acting user is the content owner OR holds a delete permission.
     * Owner identity is resolved upstream (service / repository layer) and passed in
     * so this method stays infrastructure-free.
     */
    public function assertCanDelete(
        int $actingUserId,
        int $siteId,
        ?int $ownerUserId,
        string $contentType = 'content',
    ): void {
        if ($ownerUserId !== null && $actingUserId === $ownerUserId) {
            return;
        }

        $this->assertAny($actingUserId, $siteId, [
            "{$contentType}.delete",
            'content.delete',
        ], "Missing permission: {$contentType}.delete");
    }

    /**
     * Passes if the acting user is the content owner OR holds an edit permission.
     */
    public function assertCanEdit(
        int $actingUserId,
        int $siteId,
        ?int $ownerUserId,
        string $contentType = 'content',
    ): void {
        if ($ownerUserId !== null && $actingUserId === $ownerUserId) {
            return;
        }

        $this->assertAny($actingUserId, $siteId, [
            "{$contentType}.edit",
            'content.edit',
        ], "Missing permission: {$contentType}.edit");
    }

    /**
     * Passes if the acting user is the comment author OR holds a delete permission.
     */
    public function assertCanDeleteComment(
        int $actingUserId,
        int $siteId,
        ?int $commentAuthorUserId,
        string $contentType = 'content',
    ): void {
        if ($commentAuthorUserId !== null && $actingUserId === $commentAuthorUserId) {
            return;
        }

        $this->assertAny($actingUserId, $siteId, [
            "{$contentType}.comment.delete",
            'content.comment.delete',
            'content.delete',
        ], "Missing permission: {$contentType}.comment.delete");
    }

    /**
     * Passes if the acting user is the content owner OR holds a make-private permission.
     */
    public function assertCanMakePrivate(
        int $actingUserId,
        int $siteId,
        ?int $ownerUserId,
        string $contentType = 'content',
    ): void {
        if ($ownerUserId !== null && $actingUserId === $ownerUserId) {
            return;
        }

        $this->assertAny($actingUserId, $siteId, [
            "{$contentType}.make_private",
            'content.make_private',
        ], "Missing permission: {$contentType}.make_private");
    }

    /**
     * Preserve existing content.* grants while allowing the newer page/brief-specific permissions.
     */
    private function assertAny(int $userId, int $siteId, array $permissions, string $message): void
    {
        $lastException = null;

        foreach ($permissions as $permission) {
            try {
                $this->authorization->assert($userId, $siteId, $permission, $message);
                return;
            } catch (\Throwable $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException;
    }

    public function assertCanRequestChanges(int $userId, int $siteId): void
    {
        $this->assertAny($userId, $siteId, [
            ModerationPermission::PagesRequestChanges->value,
            ModerationPermission::ContentRequestChanges->value,
        ], 'Missing permission: PagesRequestChanges');
    }

    public function assertCanEscalate(int $userId, int $siteId): void
    {
        $this->assertAny($userId, $siteId, [
            ModerationPermission::PagesEscalate->value,
            ModerationPermission::ContentEscalate->value,
        ], 'Missing permission: PagesEscalate');
    }

    public function assertCanViewHighRisk(int $userId, int $siteId): void
    {
        $this->assertAny($userId, $siteId, [
            ModerationPermission::PagesViewHighRisk->value,
            ModerationPermission::ContentViewHighRisk->value,
        ], 'Missing permission: PagesViewHighRisk');
    }

    public function assertCanAssignReview(int $userId, int $siteId): void
    {
        $this->assertAny($userId, $siteId, [
            ModerationPermission::PagesAssignReview->value,
            ModerationPermission::ContentAssignReview->value,
        ], 'Missing permission: PagesAssignReview');
    }

    public function assertCanOverridePriority(int $userId, int $siteId): void
    {
        $this->assertAny($userId, $siteId, [
            ModerationPermission::PagesOverridePriority->value,
            ModerationPermission::ContentOverridePriority->value,
        ], 'Missing permission: PagesOverridePriority');
    }

    public function assertCanResolveRisk(int $userId, int $siteI): void
    {
        $this->assertAny($userId, $siteI, [
            ModerationPermission::PagesResolveRisk->value,
            ModerationPermission::ContentResolveRisk->value,
        ], 'Missing permission: PagesResolveRisk');
    }
}