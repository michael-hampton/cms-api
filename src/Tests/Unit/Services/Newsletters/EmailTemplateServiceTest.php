<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Database\Database;
use App\Models\EmailTemplate;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Repositories\Newsletters\EmailThemeRepository;
use App\Services\Newsletter\EmailTemplateRenderer;
use App\Services\Newsletter\EmailTemplateService;
use App\Services\Newsletter\PreviewDataFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class EmailTemplateServiceTest extends FunctionalTestCase
{
    private MockInterface $db;
    private MockInterface $repository;
    private MockInterface $themeRepository;
    private MockInterface $renderer;
    private PreviewDataFactory $previewDataFactory;
    private EmailTemplateService $service;

    public function test_create_generates_unique_slug_and_sanitises_blocks(): void
    {
        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('welcome-email', 1, null)
            ->once()
            ->andReturn(false);
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return $data['slug'] === 'welcome-email'
                    && $data['site_id'] === 1
                    && $data['blocks'][0]['type'] === 'text'
                    && $data['blocks'][0]['visible'] === true;
            }))
            ->andReturn(new EmailTemplate(['id' => 10]));

        $template = $this->service->create([
            'name' => 'Welcome Email',
            'category' => 'transactional',
            'blocks' => [['data' => ['content' => 'Hello']]],
        ], 1);

        $this->assertInstanceOf(EmailTemplate::class, $template);
    }

    public function test_update_regenerates_slug_when_name_changes(): void
    {
        $template = new EmailTemplate([
            'id' => 5,
            'name' => 'Old Name',
            'slug' => 'old-name',
            'site_id' => 3,
        ]);

        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('find')->with(5)->once()->andReturn($template);
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('new-name', 3, 5)
            ->once()
            ->andReturn(false);
        $this->repository->shouldReceive('update')
            ->with(5, Mockery::on(fn(array $data): bool => $data['slug'] === 'new-name'))
            ->once()
            ->andReturn($template);

        $result = $this->service->update(5, ['name' => 'New Name']);

        $this->assertSame($template, $result);
    }

    public function test_preview_saved_returns_html_plain_text_and_unresolved_tokens(): void
    {
        $template = new EmailTemplate([
            'id' => 9,
            'site_id' => 1,
            'theme_id' => null,
            'name' => 'Order Template',
            'blocks' => [['type' => 'text', 'data' => ['content' => 'Hi {{ user.first_name }}']]],
        ]);

        $this->repository->shouldReceive('find')->with(9)->once()->andReturn($template);
        $this->renderer->shouldReceive('render')->once()->andReturn('<div>Hello {{ missing.value }}</div>');

        $result = $this->service->previewSaved(9, 'mock_user');

        $this->assertArrayHasKey('html', $result);
        $this->assertSame('Hello {{ missing.value }}', $result['plain_text']);
        $this->assertSame(['missing.value'], $result['unresolved_tokens']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(EmailTemplateRepository::class);
        $this->themeRepository = Mockery::mock(EmailThemeRepository::class);
        $this->renderer = Mockery::mock(EmailTemplateRenderer::class);
        $this->previewDataFactory = new PreviewDataFactory();

        $this->service = new EmailTemplateService(
            $this->db,
            $this->repository,
            $this->themeRepository,
            $this->renderer,
            $this->previewDataFactory,
        );
    }
}
