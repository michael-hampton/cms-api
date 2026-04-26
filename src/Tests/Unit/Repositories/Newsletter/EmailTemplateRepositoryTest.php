<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\NewsletterLayout;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EmailTemplateRepositoryTest extends RepositoryTestCase
{
    private EmailTemplateRepository $repository;

    public function test_get_all_by_site_filters_and_sorts_templates(): void
    {
        NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Bravo',
            'slug' => 'bravo',
            'category' => 'marketing',
            'layout_definition_json' => [],
            'is_active' => true,
            'type' => NewsletterLayout::TYPE_EMAIL_TEMPLATE
        ]);
        NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Alpha',
            'slug' => 'alpha',
            'category' => 'marketing',
            'layout_definition_json' => [],
            'is_active' => true,
            'type' => NewsletterLayout::TYPE_EMAIL_TEMPLATE
        ]);
        $otherSite = $this->createSite(['slug' => 'other-site-' . uniqid(), 'is_default' => false]);

        NewsletterLayout::create([
            'site_id' => $otherSite->id,
            'name' => 'Other Site',
            'slug' => 'other-site',
            'category' => 'marketing',
            'layout_definition_json' => [],
            'is_active' => true,
            'type' => NewsletterLayout::TYPE_EMAIL_TEMPLATE
        ]);

        $result = $this->repository->getAllBySite($this->siteId, 'marketing');

        $this->assertCount(2, $result);
        $names = $result->map(fn(NewsletterLayout $template) => $template->name)->toArray();
        $this->assertSame(['Alpha', 'Bravo'], $names);
    }

    public function test_slug_exists_for_site_honours_excluded_id(): void
    {
        $template = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Welcome',
            'slug' => 'welcome',
            'category' => 'transactional',
            'layout_definition_json' => [],
            'is_active' => true,
            'type' => NewsletterLayout::TYPE_EMAIL_TEMPLATE
        ]);

        $this->assertTrue($this->repository->slugExistsForSite('welcome', $this->siteId));
        $this->assertFalse($this->repository->slugExistsForSite('welcome', $this->siteId, $template->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EmailTemplateRepository(app(NewsletterLayoutRepository::class));
    }
}
