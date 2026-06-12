<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Events\OpenCollab\GuidelineArchivedEvent;
use App\Events\OpenCollab\GuidelineDraftCreatedEvent;
use App\Events\OpenCollab\GuidelinePublishedEvent;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Exceptions\OpenCollab\GuidelineNotArchivableException;
use App\Exceptions\OpenCollab\GuidelineNotEditableException;
use App\Exceptions\OpenCollab\GuidelineNotPublishableException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Models\Guideline;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;

/**
 * Orchestrates the guideline authoring and publishing lifecycle.
 *
 * Responsibilities:
 *   - Validate lifecycle transitions
 *   - Coordinate repository writes inside transaction boundaries
 *   - Keep Site.guidelines_version pointer in sync on publish
 *   - Emit domain events for side-effects (fanout, audit)
 *
 * Does NOT:
 *   - Format data for presentation
 *   - Access sessions or request globals
 *   - Build queries
 */
class GuidelineService
{
    public function __construct(
        private readonly GuidelinesContentRepository $guidelinesRepository,
        private readonly Database                    $database,
        private readonly SiteRepository              $siteRepository,
    )
    {
    }

    // ── Draft Creation ────────────────────────────────────────────────────────

    public function createDraft(
        int $siteId,
        string $content,
        int $createdByUserId,
        array $metadata = [],
    ): Guideline
    {
        return $this->database->transaction(function () use ($siteId, $content, $createdByUserId, $metadata): Guideline {
            $guideline = empty($metadata)
                ? $this->guidelinesRepository->createVersion($siteId, $content)
                : $this->guidelinesRepository->createVersion($siteId, $content, $metadata + [
                    'source_type' => 'manual',
                    'content_format' => 'html',
                    'extraction_status' => 'not_required',
                ]);

            event(new GuidelineDraftCreatedEvent(
                guideline: $guideline,
                siteId: $siteId,
                clonedFromGuidelineId: null,
                sourceTemplateId: null,
            ));

            return $guideline;
        });
    }

    // ── Edit Guard ────────────────────────────────────────────────────────────

    public function updateDraftContent(Guideline $guideline, string $content): Guideline
    {
        $this->assertEditable($guideline);

        return $this->database->transaction(function () use ($guideline, $content): Guideline {
            $guideline->update(['content' => $content]);

            return $guideline->fresh();
        });
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    /**
     * Publish a draft guideline and update the Site.guidelines_version pointer.
     * Optionally auto-archives the currently published version.
     */
    public function publishVersion(Guideline $guideline, int $publishedByUserId, bool $autoArchivePrevious = true): Guideline
    {
        $this->assertPublishable($guideline);

        return $this->database->transaction(function () use ($guideline, $publishedByUserId, $autoArchivePrevious): Guideline {
            if ($autoArchivePrevious) {
                $current = $this->guidelinesRepository->latestPublishedForSite($guideline->site_id);
                if ($current && $current->id !== $guideline->id) {
                    $this->guidelinesRepository->archive($current, $publishedByUserId);
                    event(new GuidelineArchivedEvent(
                        guideline: $current,
                        siteId: $guideline->site_id,
                        archivedByUserId: $publishedByUserId,
                    ));
                }
            }

            $published = $this->guidelinesRepository->publish($guideline, $publishedByUserId);

            // Keep the Site convenience pointer in sync.
            $site = $this->siteRepository->find($published->site_id);
            if ($site) {
                $this->siteRepository->update($site->id, ['guidelines_version' => $published->version]);
            }

            event(new GuidelinePublishedEvent(
                guideline: $published,
                siteId: $published->site_id,
                version: $published->version,
                publishedByUserId: $publishedByUserId,
            ));

            event(new GuidelinesVersionBumpedEvent(
                guideline: $published,
                siteId: $published->site_id,
                newVersion: $published->version,
            ));

            return $published;
        });
    }

    // ── Archive ───────────────────────────────────────────────────────────────

    public function archiveVersion(Guideline $guideline, int $archivedByUserId): Guideline
    {
        $this->assertArchivable($guideline);

        return $this->database->transaction(function () use ($guideline, $archivedByUserId): Guideline {
            $archived = $this->guidelinesRepository->archive($guideline, $archivedByUserId);

            event(new GuidelineArchivedEvent(
                guideline: $archived,
                siteId: $archived->site_id,
                archivedByUserId: $archivedByUserId,
            ));

            return $archived;
        });
    }

    // ── Clone To Draft ────────────────────────────────────────────────────────

    public function cloneToDraft(Guideline $source, int $createdByUserId): Guideline
    {
        return $this->database->transaction(function () use ($source, $createdByUserId): Guideline {
            $version = $this->guidelinesRepository->nextVersionNumber($source->site_id);

            $draft = $this->guidelinesRepository->create([
                'site_id' => $source->site_id,
                'title' => $source->title,
                'version' => $version,
                'content' => $source->content,
                'source_type' => $source->source_type,
                'content_format' => $source->content_format ?: 'html',
                'template_id' => $source->template_id,
                'document_id' => $source->document_id,
                'source_document_id' => $source->source_document_id,
                'extraction_status' => $source->extraction_status,
                'extraction_error' => $source->extraction_error,
                'status' => GuidelineStatus::Draft->value,
                'cloned_from_version_id' => $source->id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            event(new GuidelineDraftCreatedEvent(
                guideline: $draft,
                siteId: $draft->site_id,
                clonedFromGuidelineId: $source->id,
                sourceTemplateId: null,
            ));

            return $draft;
        });
    }

    public function createDraftFromDocument(
        UploadedFile $file,
        int $siteId,
        int $createdByUserId,
        ?string $title = null,
    ): Guideline {
        return $this->database->transaction(function () use ($file, $siteId, $createdByUserId, $title): Guideline {
            $documentService = app(OpenCollabDocumentService::class);
            $document = $documentService->store(
                file: $file,
                siteId: $siteId,
                category: 'published_guideline_document',
                uploadedByUserId: $createdByUserId,
                documentableType: 'guideline',
            );
            $extraction = $document->metadata_json['extraction'] ?? [];

            $guideline = $this->createDraft(
                siteId: $siteId,
                content: $extraction['content'] ?? '',
                createdByUserId: $createdByUserId,
                metadata: [
                    'title' => $title,
                    'source_type' => 'document_upload',
                    'content_format' => $extraction['format'] ?? 'document',
                    'document_id' => $document->id,
                    'source_document_id' => $document->id,
                    'extraction_status' => $extraction['status'] ?? 'failed',
                    'extraction_error' => $extraction['error'] ?? null,
                ],
            );

            $documentService->attach($document, 'guideline', $guideline->id);

            return $guideline->fresh() ?? $guideline;
        });
    }

    // ── Guard Assertions ──────────────────────────────────────────────────────

    public function assertEditable(Guideline $guideline): void
    {
        if ($guideline->status === GuidelineStatus::Published->value) {
            throw GuidelineNotEditableException::alreadyPublished($guideline->id);
        }
        if ($guideline->status === GuidelineStatus::Archived->value) {
            throw GuidelineNotEditableException::alreadyArchived($guideline->id);
        }
    }

    public function assertPublishable(Guideline $guideline): void
    {
        $status = GuidelineStatus::from($guideline->status);

        if (!$status->isPublishable()) {
            throw GuidelineNotPublishableException::notDraft($guideline->id, $status->value);
        }
    }

    public function assertArchivable(Guideline $guideline): void
    {
        $status = GuidelineStatus::from($guideline->status);

        if (!$status->isArchivable()) {
            throw GuidelineNotArchivableException::notPublished($guideline->id, $status->value);
        }
    }
}
