<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Events\OpenCollab\ContractArchivedEvent;
use App\Events\OpenCollab\ContractDraftCreatedEvent;
use App\Events\OpenCollab\ContractPublishedEvent;
use App\Exceptions\OpenCollab\ContractNotArchivableException;
use App\Exceptions\OpenCollab\ContractNotEditableException;
use App\Exceptions\OpenCollab\ContractNotPublishableException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Models\Contract;
use App\Repositories\OpenCollab\ContractRepository;

/**
 * Orchestrates the contract authoring and publishing lifecycle.
 *
 * Responsibilities:
 *   - Validate lifecycle transitions
 *   - Coordinate repository writes inside transaction boundaries
 *   - Emit domain events for side-effects (fanout, audit)
 *
 * Does NOT:
 *   - Format data for presentation
 *   - Access sessions or request globals
 *   - Build queries
 */
class ContractService
{
    public function __construct(
        private readonly ContractRepository $contractRepository,
        private readonly Database           $database,
    )
    {
    }

    // ── Draft Creation ────────────────────────────────────────────────────────

    /**
     * Create a blank draft contract for a site.
     */
    public function createDraft(
        int $siteId,
        string $content,
        int $createdByUserId,
        array $metadata = [],
    ): Contract
    {
        return $this->database->transaction(function () use ($siteId, $content, $createdByUserId, $metadata): Contract {
            $version = $this->contractRepository->nextVersionNumber($siteId);

            $contract = $this->contractRepository->create([
                'site_id' => $siteId,
                'title' => $metadata['title'] ?? null,
                'version' => $version,
                'content' => $content,
                'source_type' => $metadata['source_type'] ?? 'manual',
                'content_format' => $metadata['content_format'] ?? 'html',
                'template_id' => $metadata['template_id'] ?? null,
                'document_id' => $metadata['document_id'] ?? null,
                'source_document_id' => $metadata['source_document_id'] ?? null,
                'extraction_status' => $metadata['extraction_status'] ?? 'not_required',
                'extraction_error' => $metadata['extraction_error'] ?? null,
                'status' => ContractStatus::Draft->value,
                'issued_by_user_id' => $createdByUserId,
                'issued_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            event(new ContractDraftCreatedEvent(
                contract: $contract,
                siteId: $siteId,
                clonedFromContractId: null,
                sourceTemplateId: null,
            ));

            return $contract;
        });
    }

    // ── Edit Guard ────────────────────────────────────────────────────────────

    /**
     * Update draft content. Throws if the contract is not in draft status.
     */
    public function updateDraftContent(Contract $contract, string $content): Contract
    {
        $this->assertEditable($contract);

        return $this->database->transaction(function () use ($contract, $content): Contract {
            $contract->update(['content' => $content]);

            return $contract->fresh();
        });
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    /**
     * Publish a draft contract, optionally auto-archiving any currently
     * published version for this site.
     *
     * Only one published version per site is considered active.
     */
    public function publishVersion(Contract $contract, int $publishedByUserId, bool $autoArchivePrevious = true): Contract
    {
        $this->assertPublishable($contract);

        return $this->database->transaction(function () use ($contract, $publishedByUserId, $autoArchivePrevious): Contract {
            if ($autoArchivePrevious) {
                $current = $this->contractRepository->latestPublishedForSite($contract->site_id);
                if ($current && $current->id !== $contract->id) {
                    $this->contractRepository->archive($current, $publishedByUserId);
                    event(new ContractArchivedEvent(
                        contract: $current,
                        siteId: $contract->site_id,
                        archivedByUserId: $publishedByUserId,
                    ));
                }
            }

            $published = $this->contractRepository->publish($contract, $publishedByUserId);

            event(new ContractPublishedEvent(
                contract: $published,
                siteId: $published->site_id,
                version: $published->version,
                publishedByUserId: $publishedByUserId,
            ));

            return $published;
        });
    }

    // ── Archive ───────────────────────────────────────────────────────────────

    public function archiveVersion(Contract $contract, int $archivedByUserId): Contract
    {
        $this->assertArchivable($contract);

        return $this->database->transaction(function () use ($contract, $archivedByUserId): Contract {
            $archived = $this->contractRepository->archive($contract, $archivedByUserId);

            event(new ContractArchivedEvent(
                contract: $archived,
                siteId: $archived->site_id,
                archivedByUserId: $archivedByUserId,
            ));

            return $archived;
        });
    }

    // ── Clone To Draft ────────────────────────────────────────────────────────

    /**
     * Duplicate an existing contract (any status) as a new draft.
     * The original remains unchanged — content snapshot is taken at clone time.
     */
    public function cloneToDraft(Contract $source, int $createdByUserId): Contract
    {
        return $this->database->transaction(function () use ($source, $createdByUserId): Contract {
            $version = $this->contractRepository->nextVersionNumber($source->site_id);

            $draft = $this->contractRepository->create([
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
                'status' => ContractStatus::Draft->value,
                'cloned_from_version_id' => $source->id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            event(new ContractDraftCreatedEvent(
                contract: $draft,
                siteId: $draft->site_id,
                clonedFromContractId: $source->id,
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
    ): Contract {
        return $this->database->transaction(function () use ($file, $siteId, $createdByUserId, $title): Contract {
            $documentService = app(OpenCollabDocumentService::class);
            $document = $documentService->store(
                file: $file,
                siteId: $siteId,
                category: 'issued_contract_document',
                uploadedByUserId: $createdByUserId,
                documentableType: 'contract',
            );
            $extraction = $document->metadata_json['extraction'] ?? [];

            $contract = $this->createDraft(
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

            $documentService->attach($document, 'contract', $contract->id);

            return $contract->fresh() ?? $contract;
        });
    }

    // ── Guard Assertions ──────────────────────────────────────────────────────

    public function assertEditable(Contract $contract): void
    {
        if ($contract->status === ContractStatus::Published->value) {
            throw ContractNotEditableException::alreadyPublished($contract->id);
        }
        if ($contract->status === ContractStatus::Archived->value) {
            throw ContractNotEditableException::alreadyArchived($contract->id);
        }
    }

    public function assertPublishable(Contract $contract): void
    {
        // Convert raw string to Enum instance
        $status = ContractStatus::from($contract->status);

        if (!$status->isPublishable()) {
            throw ContractNotPublishableException::notDraft($contract->id, $status->value);
        }
    }

    public function assertArchivable(Contract $contract): void
    {
        // Convert raw string to Enum instance
        $status = ContractStatus::from($contract->status);

        if (!$status->isArchivable()) {
            throw ContractNotArchivableException::notPublished($contract->id, $status->value);
        }
    }
}
