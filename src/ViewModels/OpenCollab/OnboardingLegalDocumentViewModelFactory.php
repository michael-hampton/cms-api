<?php

namespace App\ViewModels\OpenCollab;

use App\Models\Contract;
use App\Models\Guideline;
use App\Models\OpenCollabDocument;
use App\Models\TermsVersion;
use App\Services\OpenCollab\OpenCollabDocumentService;

class OnboardingLegalDocumentViewModelFactory
{
    public function __construct(
        private readonly OpenCollabDocumentService $documentService,
    ) {
    }

    public function forTerms(?TermsVersion $terms): ?array
    {
        if (!$terms) {
            return null;
        }

        return $this->build(
            id: (int)$terms->id,
            type: 'terms',
            title: $terms->title ?: 'Terms & Conditions',
            version: (string)$terms->semantic_version,
            content: $terms->rendered_content ?: $terms->source_content,
            contentFormat: $terms->rendered_format ?: $terms->source_format ?: 'html',
            documentId: $terms->document_id ?: $terms->source_document_id,
            metadata: [
                'isMaterialChange' => (bool)$terms->is_material_change,
                'changeSummary' => $terms->change_summary,
                'renderedHash' => $terms->rendered_hash,
            ],
        );
    }

    public function forContract(?Contract $contract): ?array
    {
        if (!$contract) {
            return null;
        }

        return $this->build(
            id: (int)$contract->id,
            type: 'contract',
            title: $contract->title ?: 'Contributor Agreement',
            version: (string)$contract->version,
            content: $contract->content,
            contentFormat: $contract->content_format ?: 'html',
            documentId: $contract->document_id ?: $contract->source_document_id,
        );
    }

    public function forGuideline(?Guideline $guideline): ?array
    {
        if (!$guideline) {
            return null;
        }

        return $this->build(
            id: (int)$guideline->id,
            type: 'guideline',
            title: $guideline->title ?: 'Brand & Editorial Guidelines',
            version: (string)$guideline->version,
            content: $guideline->content,
            contentFormat: $guideline->content_format ?: 'html',
            documentId: $guideline->document_id ?: $guideline->source_document_id,
        );
    }

    private function build(
        int $id,
        string $type,
        string $title,
        string $version,
        ?string $content,
        string $contentFormat,
        ?int $documentId,
        array $metadata = [],
    ): array {
        $document = $documentId ? OpenCollabDocument::find((int)$documentId) : null;
        $hasInlineContent = trim((string)$content) !== '' && in_array($contentFormat, ['html', 'text'], true);

        return array_merge([
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'version' => $version,
            'mode' => $hasInlineContent ? 'html' : 'document',
            'content' => $hasInlineContent ? $content : null,
            'documentUrl' => $document ? $this->documentService->previewUrl($document) : null,
            'downloadUrl' => $document ? $this->documentService->downloadUrl($document) : null,
            'filename' => $document?->original_filename,
            'mimeType' => $document?->mime_type,
            'accepted' => false,
        ], $metadata);
    }
}
