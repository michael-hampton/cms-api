<?php

namespace App\Services\Newsletter;

use App\Enums\Newsletters\LayoutVersionState;
use App\Events\Newsletters\NewsletterLayoutPublished;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;
use App\Repositories\Newsletters\NewsletterLayoutRepository;

/**
 * Orchestrates layout lifecycle: creation, versioning, state transitions, cloning.
 * System layouts cannot be deleted — only user-created layouts can be removed.
 */
class NewsletterLayoutService
{
    public function __construct(
        private readonly NewsletterLayoutRepository $layoutRepository,
        private readonly LayoutRendererService      $layoutRenderer,
        private readonly Logger                     $logger,
        private readonly Database                   $database
    )
    {
    }

    /**
     * Create a new layout with its initial draft version.
     */
    public function createLayout(
        string $name,
        string $slug,
        array  $layoutDefinition,
        bool   $isSystemLayout = false,
        ?int   $createdBy = null
    ): NewsletterLayout
    {
        return $this->database->transaction(function () use ($name, $slug, $layoutDefinition, $isSystemLayout, $createdBy) {
            if ($this->layoutRepository->findBySlug($slug)) {
                throw new \InvalidArgumentException("Layout slug '{$slug}' is already in use.");
            }

            $layout = NewsletterLayout::create([
                'name' => $name,
                'slug' => $slug,
                'layout_definition_json' => $layoutDefinition,
                'is_system_layout' => $isSystemLayout,
                'created_by' => $createdBy,
            ]);

            $this->layoutRepository->createVersion(
                $layout->id,
                $layoutDefinition,
                1,
                LayoutVersionState::Draft->value
            );

            return $layout;
        });
    }

    /**
     * Add a new version to an existing layout.
     * Only draft/validated versions can be added on top of a published layout.
     */
    public function addLayoutVersion(
        int     $layoutId,
        array   $layoutDefinition,
        ?string $migrationScriptReference = null
    ): NewsletterLayoutVersion
    {
        return $this->database->transaction(function () use ($layoutId, $layoutDefinition, $migrationScriptReference) {
            $layout = NewsletterLayout::find($layoutId);

            if (!$layout) {
                throw new \InvalidArgumentException("Layout ID {$layoutId} not found.");
            }

            $nextVersion = $this->layoutRepository->nextVersionNumber($layoutId);

            return $this->layoutRepository->createVersion(
                $layoutId,
                $layoutDefinition,
                $nextVersion,
                LayoutVersionState::Draft->value,
                $migrationScriptReference
            );
        });
    }

    /**
     * Transition a layout version's state.
     * Validates that the transition is legal per the state machine.
     */
    public function transitionVersionState(int $versionId, LayoutVersionState $targetState): NewsletterLayoutVersion
    {
        return $this->database->transaction(function () use ($versionId, $targetState) {
            $version = $this->layoutRepository->findVersionById($versionId);

            if (!$version) {
                throw new \InvalidArgumentException("Layout version ID {$versionId} not found.");
            }

            $currentState = $version->state();

            if (!$currentState->canTransitionTo($targetState)) {
                throw new \RuntimeException(
                    "Cannot transition layout version from '{$currentState->label()}' to '{$targetState->label()}'."
                );
            }

            $this->layoutRepository->updateVersionState($versionId, $targetState);
            $version = $this->layoutRepository->findVersionById($versionId);

            if ($targetState === LayoutVersionState::Published) {
                event(new NewsletterLayoutPublished($version));

                $this->logger->info('Newsletter layout version published', [
                    'layout_id' => $version->layout_id,
                    'version_number' => $version->version_number,
                ]);
            }

            return $version;
        });
    }

    /**
     * Clone an existing layout into a new user-owned layout.
     * Cloned version starts as Draft.
     */
    public function cloneLayout(int $sourceLayoutId, string $newName, string $newSlug, int $clonedBy): NewsletterLayout
    {
        return $this->database->transaction(function () use ($sourceLayoutId, $newName, $newSlug, $clonedBy) {
            if ($this->layoutRepository->findBySlug($newSlug)) {
                throw new \InvalidArgumentException("Layout slug '{$newSlug}' is already taken.");
            }

            return $this->layoutRepository->cloneLayout($sourceLayoutId, $newName, $newSlug, $clonedBy);
        });
    }

    /**
     * Delete a layout. System layouts cannot be deleted.
     */
    public function deleteLayout(int $layoutId): void
    {
        $layout = NewsletterLayout::find($layoutId);

        if (!$layout) {
            throw new \InvalidArgumentException("Layout ID {$layoutId} not found.");
        }

        if (!$layout->isDeletable()) {
            throw new \RuntimeException("System layouts cannot be deleted.");
        }

        // No writes outside transaction
        $this->database->transaction(function () use ($layoutId) {
            NewsletterLayout::find($layoutId)->delete();
        });
    }

    public function getAllLayouts(): Collection
    {
        return $this->layoutRepository->allPublishedLayouts();
    }

    public function getSystemLayouts(): Collection
    {
        return $this->layoutRepository->allSystemLayouts();
    }

    public function getLayoutVersionHistory(int $layoutId): Collection
    {
        return $this->layoutRepository->versionHistory($layoutId);
    }

    /**
     * Build a slot migration diff report between two layout versions.
     */
    public function buildMigrationReport(int $oldVersionId, int $newVersionId): array
    {
        $oldVersion = $this->layoutRepository->findVersionById($oldVersionId);
        $newVersion = $this->layoutRepository->findVersionById($newVersionId);

        if (!$oldVersion || !$newVersion) {
            throw new \InvalidArgumentException("One or both version IDs are invalid.");
        }

        return $this->layoutRenderer->buildSlotMigrationReport($oldVersion, $newVersion);
    }
}