<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\ImageAuditAction;
use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\ActivityRepository;

/**
 * Records image selection audit events.
 *
 * Uses the existing ActivityRepository so image audit records appear in the
 * same activity feed as other contributor actions. The payload carries
 * page_id, block_id, cms_image_id, and previous_cms_image_id where relevant.
 *
 * Called by the ContributorPageService when blocks are saved — not on every
 * keystroke, only on actual save.
 */
class ImageAuditService
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly Logger $logger,
    ) {
    }

    public function recordAttach(
        int    $siteId,
        int    $userId,
        int    $pageId,
        string $blockId,
        int    $cmsImageId,
    ): void {
        $this->record($siteId, $userId, ImageAuditAction::Attached, [
            'page_id'      => $pageId,
            'block_id'     => $blockId,
            'cms_image_id' => $cmsImageId,
            'action'       => ImageAuditAction::Attached->value,
        ]);
    }

    public function recordReplace(
        int    $siteId,
        int    $userId,
        int    $pageId,
        string $blockId,
        int    $newCmsImageId,
        int    $previousCmsImageId,
    ): void {
        $this->record($siteId, $userId, ImageAuditAction::Replaced, [
            'page_id'               => $pageId,
            'block_id'              => $blockId,
            'cms_image_id'          => $newCmsImageId,
            'previous_cms_image_id' => $previousCmsImageId,
            'action'                => ImageAuditAction::Replaced->value,
        ]);
    }

    public function recordRemove(
        int    $siteId,
        int    $userId,
        int    $pageId,
        string $blockId,
        int    $cmsImageId,
    ): void {
        $this->record($siteId, $userId, ImageAuditAction::Removed, [
            'page_id'      => $pageId,
            'block_id'     => $blockId,
            'cms_image_id' => $cmsImageId,
            'action'       => ImageAuditAction::Removed->value,
        ]);
    }

    /**
     * Diff previous and current blocks and record any image changes.
     * Called on article save — deduplication happens by comparing old vs new.
     *
     * @param array[] $previousBlocks
     * @param array[] $currentBlocks
     */
    public function diffAndRecord(
        int   $siteId,
        int   $userId,
        int   $pageId,
        array $previousBlocks,
        array $currentBlocks,
    ): void {
        $prevImageMap = $this->buildImageMap($previousBlocks);
        $currImageMap = $this->buildImageMap($currentBlocks);

        // Attached — block is new or image_id changed from null
        foreach ($currImageMap as $blockId => $cmsImageId) {
            if (!isset($prevImageMap[$blockId])) {
                $this->recordAttach($siteId, $userId, $pageId, $blockId, $cmsImageId);
            } elseif ($prevImageMap[$blockId] !== $cmsImageId) {
                $this->recordReplace($siteId, $userId, $pageId, $blockId, $cmsImageId, $prevImageMap[$blockId]);
            }
        }

        // Removed — block had an image but now doesn't
        foreach ($prevImageMap as $blockId => $cmsImageId) {
            if (!isset($currImageMap[$blockId])) {
                $this->recordRemove($siteId, $userId, $pageId, $blockId, $cmsImageId);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function record(int $siteId, int $userId, ImageAuditAction $action, array $payload): void
    {
        try {
            $this->activityRepository->record(
                siteId:  $siteId,
                userId:  $userId,
                type:    ActivityEventType::ArticleUpdated,
                payload: $payload,
            );
        } catch (\Throwable $e) {
            // Audit is non-critical — never block the primary operation —
            // but the failure should still be visible in the logs.
            $this->logger->warning('Failed to record image audit event.', [
                'site_id' => $siteId,
                'user_id' => $userId,
                'action' => $action->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a map of blockId → cmsImageId from a blocks array.
     * Only includes image blocks that have a cms_image_id set.
     *
     * @return array<string, int>
     */
    private function buildImageMap(array $blocks): array
    {
        $map = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') !== 'image') {
                continue;
            }
            $cmsImageId = $block['cms_image_id'] ?? null;
            if ($cmsImageId === null) {
                continue;
            }
            // Use block id if present; fall back to a positional key
            $blockId       = $block['id'] ?? ('block_' . count($map));
            $map[$blockId] = (int) $cmsImageId;
        }
        return $map;
    }
}