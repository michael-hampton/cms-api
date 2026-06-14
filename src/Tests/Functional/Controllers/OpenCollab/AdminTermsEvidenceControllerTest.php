<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;

class AdminTermsEvidenceControllerTest extends FunctionalTestCase
{
    public function test_missing_evidence_returns_404(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/terms-evidence/999999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_evidence_route_requires_authentication(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/admin/terms-evidence/1');

        $this->assertResponseStatus(401, $response);
    }
}
