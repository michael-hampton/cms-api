<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ConsentTypeAdminApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_returns_consent_types(): void
    {
        $this->createConsentType(['code' => 'analytics']);

        $response = $this->getForSite('/api/admin/consent-types');

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertCount(1, $body['data']);
    }

    public function test_store_creates_consent_type(): void
    {
        $response = $this->postForSite('/api/admin/consent-types', [
            'code' => 'email_marketing',
            'name' => 'Email Marketing',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => ['email'],
            'description' => 'test'
        ]);

        $this->assertResponseStatus(201, $response);
        $this->assertDatabaseHas('consent_types', ['code' => 'email_marketing']);
    }

    public function test_update_changes_consent_type(): void
    {
        $consentType = $this->createConsentType(['code' => 'analytics']);

        $response = $this->putForSite("/api/admin/consent-types/{$consentType->id}", [
            'name' => 'Analytics Cookies',
            'is_active' => false,
        ]);

        $this->assertResponseOk($response);
        $this->assertDatabaseHas('consent_types', ['id' => $consentType->id, 'name' => 'Analytics Cookies']);
    }

    public function test_destroy_deletes_consent_type(): void
    {
        $consentType = $this->createConsentType();

        $response = $this->deleteForSite("/api/admin/consent-types/{$consentType->id}");

        $this->assertResponseOk($response);
        $this->assertDatabaseMissing('consent_types', ['id' => $consentType->id]);
    }

    public function test_show_returns_consent_type_details(): void
    {
        $consentType = $this->createConsentType(['code' => 'marketing', 'name' => 'Marketing']);

        $response = $this->getForSite("/api/admin/consent-types/{$consentType->id}");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertEquals('marketing', $body['code']);
        $this->assertEquals('Marketing', $body['name']);
    }

    public function test_store_fails_with_missing_required_fields(): void
    {
        $response = $this->postForSite('/api/admin/consent-types', [
            'name' => 'Incomplete',
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_show_returns_404_when_not_found(): void
    {
        $response = $this->getForSite('/api/admin/consent-types/9999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_update_returns_404_when_not_found(): void
    {
        $response = $this->putForSite('/api/admin/consent-types/9999', [
            'name' => 'New Name'
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_destroy_returns_404_when_not_found(): void
    {
        $response = $this->deleteForSite('/api/admin/consent-types/9999');

        $this->assertResponseStatus(404, $response);
    }
}
