<?php

namespace App\Tests\Unit\Resources\OpenCollab;

use App\DTO\OpenCollab\TermsAcceptanceEvidence;
use App\Resources\OpenCollab\TermsAcceptanceEvidenceResource;
use PHPUnit\Framework\TestCase;

class TermsAcceptanceEvidenceResourceTest extends TestCase
{
    public function test_resource_returns_evidence_payload(): void
    {
        $evidence = new TermsAcceptanceEvidence(
            acceptanceId: 1,
            siteId: 2,
            userId: 3,
            termsVersionId: 4,
            semanticVersion: '1.0.0',
            renderedHash: str_repeat('a', 64),
            hashValid: true,
            acceptedAt: '2026-06-14 12:00:00',
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            acceptedVia: 'onboarding',
            renderedContent: '<p>Terms</p>',
        );

        $data = (new TermsAcceptanceEvidenceResource($evidence))->toArray();

        $this->assertSame(1, $data['acceptanceId']);
        $this->assertSame('1.0.0', $data['semanticVersion']);
        $this->assertTrue($data['hashValid']);
        $this->assertSame('<p>Terms</p>', $data['renderedContent']);
    }
}
