<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Events\OpenCollab\ContractDraftCreatedEvent;
use App\Framework\Database\Database;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Model;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContractTemplateRepository;

/**
 * Manages contract template CRUD and draft creation from templates.
 *
 * Templates are NOT compliance entities. When a draft contract is created
 * from a template, a full content snapshot is taken at that moment.
 * Subsequent template edits never affect existing contracts.
 */
class ContractTemplateService
{
    public function __construct(
        private readonly ContractTemplateRepository $templateRepository,
        private readonly ContractRepository         $contractRepository,
        private readonly Database                   $database
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
    ): ContractTemplate
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
        ContractTemplate $template,
        string           $name,
        string           $content,
        int              $updatedByUserId,
        ?string          $description = null,
    ): ContractTemplate
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

    public function deactivate(ContractTemplate $template, int $updatedByUserId): ContractTemplate
    {
        return $this->database->transaction(function () use ($template, $updatedByUserId): Model {
            $template->update(['is_active' => false, 'updated_by' => $updatedByUserId]);

            return $template->fresh();
        });
    }

    // ── Draft From Template ───────────────────────────────────────────────────

    /**
     * Create a new draft contract using the template's content as a snapshot.
     * The template itself is not mutated.
     */
    public function createDraftFromTemplate(
        ContractTemplate $template,
        int              $siteId,
        int              $createdByUserId,
    ): Contract
    {
        return $this->database->transaction(function () use ($template, $siteId, $createdByUserId): Contract {
            $version = $this->contractRepository->nextVersionNumber($siteId);

            $contract = $this->contractRepository->create([
                'site_id' => $siteId,
                'version' => $version,
                'content' => $template->content, // snapshot
                'status' => ContractStatus::Draft->value,
                'source_template_id' => $template->id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            event(new ContractDraftCreatedEvent(
                contract: $contract,
                siteId: $siteId,
                clonedFromContractId: null,
                sourceTemplateId: $template->id,
            ));

            return $contract;
        });
    }
}