<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\TermsVersionStatus;
use App\Models\TermsVersion;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class TermsOnboardingControllerTest extends FunctionalTestCase
{
    public function test_show_returns_current_published_terms(): void
    {
        $terms = TermsVersion::create([
            'site_id' => $this->siteId,
            'semantic_version' => '1.0.0',
            'title' => 'Contributor Terms',
            'source_format' => 'html',
            'source_content' => '<p>Terms source</p>',
            'rendered_format' => 'html',
            'rendered_content' => '<p>Terms snapshot</p>',
            'rendered_hash' => hash('sha256', '<p>Terms snapshot</p>'),
            'status' => TermsVersionStatus::Published->value,
            'is_material_change' => true,
            'source_type' => 'manual',
            'extraction_status' => 'not_required',
            'published_at' => '2026-06-14 12:00:00',
            'created_by_user_id' => 1,
        ]);

        $response = $this->getForSite('/api/open-collab/onboarding/terms');

        $this->assertResponseStatus(200, $response);
        $body = $this->decodeJson($response);
        $this->assertSame($terms->id, $body['terms']['id']);
        $this->assertSame('1.0.0', $body['terms']['version']);
        $this->assertTrue($body['acceptance_required']);
    }

    public function test_accept_rejects_stale_terms_version(): void
    {
        TermsVersion::create([
            'site_id' => $this->siteId,
            'semantic_version' => '2.0.0',
            'title' => 'Current Terms',
            'source_format' => 'html',
            'source_content' => '<p>Current source</p>',
            'rendered_format' => 'html',
            'rendered_content' => '<p>Current snapshot</p>',
            'rendered_hash' => hash('sha256', '<p>Current snapshot</p>'),
            'status' => TermsVersionStatus::Published->value,
            'is_material_change' => true,
            'source_type' => 'manual',
            'extraction_status' => 'not_required',
            'published_at' => '2026-06-14 12:00:00',
            'created_by_user_id' => 1,
        ]);

        $response = $this->postForSite('/api/open-collab/onboarding/terms', [
            'terms_version_id' => 999,
            'agreed' => true,
        ]);

        $this->assertResponseStatus(409, $response);
    }

    public function test_routes_require_authentication(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/onboarding/terms');

        $this->assertResponseStatus(401, $response);
    }
}
