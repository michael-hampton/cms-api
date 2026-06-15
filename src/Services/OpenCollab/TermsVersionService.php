<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\TermsVersionStatus;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\ValueObjects\OpenCollab\SemanticVersion;
use RuntimeException;

class TermsVersionService
{
    public function __construct(
        private readonly TermsVersionRepository $repository,
        private readonly OpenCollabDocumentService $documentService,
        private readonly Database $database,
        private readonly TermsLifecycleEventService $events,
    ) {
    }

    public function createDraft(int $siteId, string $semanticVersion, string $title, string $content, int $userId, array $metadata = []): TermsVersion
    {
        $semanticVersion = SemanticVersion::fromString($semanticVersion)->value();

        return $this->database->transaction(function () use ($siteId, $semanticVersion, $title, $content, $userId, $metadata): TermsVersion {
            return $this->repository->create([
                'site_id' => $siteId,
                'semantic_version' => $semanticVersion,
                'title' => $title,
                'source_format' => $metadata['source_format'] ?? 'html',
                'source_content' => $content,
                'rendered_format' => 'html',
                'status' => TermsVersionStatus::Draft->value,
                'is_material_change' => (bool)($metadata['is_material_change'] ?? false),
                'change_summary' => $metadata['change_summary'] ?? null,
                'supersedes_terms_version_id' => $metadata['supersedes_terms_version_id'] ?? null,
                'document_id' => $metadata['document_id'] ?? null,
                'source_document_id' => $metadata['source_document_id'] ?? null,
                'source_type' => $metadata['source_type'] ?? 'manual',
                'extraction_status' => $metadata['extraction_status'] ?? 'not_required',
                'extraction_error' => $metadata['extraction_error'] ?? null,
                'created_by_user_id' => $userId,
            ]);
        });
    }

    public function updateDraft(TermsVersion $terms, array $attributes): TermsVersion
    {
        $this->assertEditable($terms);
        $terms->update($attributes);
        return $terms->fresh() ?? $terms;
    }

    public function publish(TermsVersion $terms, int $userId): TermsVersion
    {
        $this->assertEditable($terms);

        return $this->database->transaction(function () use ($terms, $userId): TermsVersion {
            $rendered = $this->render((string)$terms->source_content, (string)$terms->source_format);
            $current = $this->repository->latestPublishedForSite((int)$terms->site_id);

            if ($current && $current->id !== $terms->id) {
                $current->update([
                    'status' => TermsVersionStatus::Archived->value,
                    'archived_at' => date('Y-m-d H:i:s'),
                    'archived_by_user_id' => $userId,
                ]);
            }

            $terms->update([
                'rendered_content' => $rendered,
                'rendered_hash' => hash('sha256', $rendered),
                'status' => TermsVersionStatus::Published->value,
                'published_at' => date('Y-m-d H:i:s'),
                'published_by_user_id' => $userId,
            ]);

            $published = $terms->fresh() ?? $terms;
            $this->events->published($published, $userId);

            return $published;
        });
    }

    public function createDraftFromDocument(UploadedFile $file, int $siteId, string $semanticVersion, string $title, int $userId, bool $material, ?string $summary): TermsVersion
    {
        return $this->database->transaction(function () use ($file, $siteId, $semanticVersion, $title, $userId, $material, $summary): TermsVersion {
            $document = $this->documentService->store($file, $siteId, 'terms_document', $userId, 'terms_version');
            $extraction = $document->metadata_json['extraction'] ?? [];
            $terms = $this->createDraft($siteId, $semanticVersion, $title, (string)($extraction['content'] ?? ''), $userId, [
                'source_format' => $extraction['format'] ?? 'document',
                'is_material_change' => $material,
                'change_summary' => $summary,
                'document_id' => $document->id,
                'source_document_id' => $document->id,
                'source_type' => 'document_upload',
                'extraction_status' => $extraction['status'] ?? 'failed',
                'extraction_error' => $extraction['error'] ?? null,
            ]);
            $this->documentService->attach($document, 'terms_version', (int)$terms->id);
            return $terms->fresh() ?? $terms;
        });
    }

    public function accept(TermsVersion $terms, int $userId, string $ipAddress, ?string $userAgent, string $via = 'onboarding'): UserTermsAcceptance
    {
        if ($terms->status !== TermsVersionStatus::Published && $terms->status !== TermsVersionStatus::Published->value) {
            throw new RuntimeException('Only published terms can be accepted.');
        }

        $acceptance = $this->repository->recordAcceptance([
            'site_id' => (int)$terms->site_id,
            'user_id' => $userId,
            'terms_version_id' => (int)$terms->id,
            'rendered_hash' => (string)$terms->rendered_hash,
            'accepted_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'accepted_via' => $via,
        ]);

        $this->events->accepted($acceptance);

        return $acceptance;
    }

    public function assertEditable(TermsVersion $terms): void
    {
        $status = $terms->status instanceof TermsVersionStatus ? $terms->status : TermsVersionStatus::from((string)$terms->status);
        if (!$status->isEditable()) {
            throw new RuntimeException('Published or archived terms cannot be edited. Create a new version instead.');
        }
    }

    private function render(string $content, string $format): string
    {
        return $format === 'html' ? trim($content) : nl2br(htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8'));
    }
}
