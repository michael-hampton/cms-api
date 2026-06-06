<?php

namespace App\Services\Cms;

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
}
