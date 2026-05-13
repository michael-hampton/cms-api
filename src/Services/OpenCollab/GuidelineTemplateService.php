<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Events\OpenCollab\GuidelineDraftCreatedEvent;
use App\Framework\Database\Database;
use App\Models\Guideline;
use App\Models\GuidelineTemplate;
use App\Models\Model;
use App\Repositories\OpenCollab\GuidelineTemplateRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;

/**
 * Manages guideline template CRUD and draft creation from templates.
 *
 * Templates are NOT compliance entities. Content snapshots are taken at
 * draft creation time; template edits never affect published guidelines.
 */
class GuidelineTemplateService
{
    public function __construct(
        private readonly GuidelineTemplateRepository $templateRepository,
        private readonly GuidelinesContentRepository $guidelinesRepository,
        private readonly Database                    $database
    )
    {
    }

    // ── Template CRUD ─────────────────────────────────────────────────────────

    public function createTemplate(
        string  $name,
        string  $slug,
        string  $content,
        int     $createdByUserId,
        ?string $description = null,
    ): GuidelineTemplate
    {
        return $this->database->transaction(function () use ($name, $slug, $content, $createdByUserId, $description): Model {
            return $this->templateRepository->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'content' => $content,
                'is_active' => true,
                'created_by' => $createdByUserId,
                'updated_by' => $createdByUserId,
            ]);
        });
    }

    public function updateTemplate(
        GuidelineTemplate $template,
        string            $name,
        string            $content,
        int               $updatedByUserId,
        ?string           $description = null,
    ): GuidelineTemplate
    {
        return $this->database->transaction(function () use ($template, $name, $content, $updatedByUserId, $description): Model {
            $template->update([
                'name' => $name,
                'description' => $description,
                'content' => $content,
                'updated_by' => $updatedByUserId,
            ]);

            return $template->fresh();
        });
    }

    public function deactivate(GuidelineTemplate $template, int $updatedByUserId): Model
    {
        return $this->database->transaction(function () use ($template, $updatedByUserId): GuidelineTemplate {
            $template->update(['is_active' => false, 'updated_by' => $updatedByUserId]);

            return $template->fresh();
        });
    }

    // ── Draft From Template ───────────────────────────────────────────────────

    public function createDraftFromTemplate(
        GuidelineTemplate $template,
        int               $siteId,
        int               $createdByUserId,
    ): Guideline
    {
        return $this->database->transaction(function () use ($template, $siteId, $createdByUserId): Guideline {
            $version = $this->guidelinesRepository->nextVersionNumber($siteId);

            $guideline = $this->guidelinesRepository->create([
                'site_id' => $siteId,
                'version' => $version,
                'content' => $template->content, // snapshot
                'status' => GuidelineStatus::Draft->value,
                'source_template_id' => $template->id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            event(new GuidelineDraftCreatedEvent(
                guideline: $guideline,
                siteId: $siteId,
                clonedFromGuidelineId: null,
                sourceTemplateId: $template->id,
            ));

            return $guideline;
        });
    }
}