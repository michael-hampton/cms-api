<?php

namespace App\Tests\Functional\Controllers\Newsletters;

use App\Models\EmailTemplate;
use App\Models\Model;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class EmailTemplateControllerTest extends FunctionalTestCase
{
    public function test_index_returns_templates(): void
    {
        $this->createTemplate(['name' => 'Alpha', 'slug' => 'alpha-template']);
        $this->createTemplate(['name' => 'Bravo', 'slug' => 'bravo-template']);

        $response = $this->getForSite('/api/email-templates');

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(2, $payload['items']);
    }

    private function createTemplate(array $overrides = []): Model
    {
        return EmailTemplate::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Order Confirmation',
            'slug' => 'order-confirmation-' . uniqid(),
            'description' => 'Transactional email',
            'category' => 'transactional',
            'blocks' => [['type' => 'text', 'data' => ['content' => 'Hi']]],
            'is_active' => true,
        ], $overrides));
    }

    public function test_store_creates_template(): void
    {
        $response = $this->postForSite('/api/email-templates', [
            'name' => 'Welcome Email',
            'category' => 'transactional',
            'blocks' => [['type' => 'text', 'data' => ['content' => 'Hello']]],
            'is_active' => true,
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Welcome Email', $payload['data']['template']['name']);
    }

    public function test_show_returns_template_by_id(): void
    {
        $template = $this->createTemplate();

        $response = $this->getForSite("/api/email-templates/{$template->id}");

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame($template->id, $payload['data']['template']['id']);
    }

    public function test_preview_from_data_returns_preview_payload(): void
    {
        $response = $this->postForSite('/api/email-templates/preview', [
            'dataset' => 'mock_user',
            'blocks' => [['type' => 'text', 'data' => ['content' => 'Hi {{ user.first_name }}']]],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('html', $payload);
        $this->assertArrayHasKey('plain_text', $payload);
        $this->assertArrayHasKey('unresolved_tokens', $payload);
    }
}
