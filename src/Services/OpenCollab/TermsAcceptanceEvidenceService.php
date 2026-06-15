<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\TermsAcceptanceEvidence;
use App\Repositories\OpenCollab\UserTermsAcceptanceRepositoryInterface;
use RuntimeException;

class TermsAcceptanceEvidenceService
{
    public function __construct(
        private readonly UserTermsAcceptanceRepositoryInterface $repository,
    ) {
    }

    public function get(int $acceptanceId): TermsAcceptanceEvidence
    {
        $acceptance = $this->repository->findWithTermsVersion($acceptanceId);

        if (!$acceptance) {
            throw new RuntimeException('Terms acceptance evidence not found.');
        }

        $terms = $acceptance->termsVersion(true);

        if (!$terms) {
            throw new RuntimeException('Accepted terms version could not be loaded.');
        }

        $renderedContent = (string)$terms->rendered_content;
        $acceptedHash = (string)$acceptance->rendered_hash;

        return new TermsAcceptanceEvidence(
            acceptanceId: (int)$acceptance->id,
            siteId: (int)$acceptance->site_id,
            userId: (int)$acceptance->user_id,
            termsVersionId: (int)$acceptance->terms_version_id,
            semanticVersion: (string)$terms->semantic_version,
            renderedHash: $acceptedHash,
            hashValid: hash_equals($acceptedHash, hash('sha256', $renderedContent)),
            acceptedAt: (string)$acceptance->accepted_at,
            ipAddress: $acceptance->ip_address,
            userAgent: $acceptance->user_agent,
            acceptedVia: (string)$acceptance->accepted_via,
            renderedContent: $renderedContent,
        );
    }
}
