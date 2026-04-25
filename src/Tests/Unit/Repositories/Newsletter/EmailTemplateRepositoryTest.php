<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\EmailTemplate;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EmailTemplateRepositoryTest extends RepositoryTestCase
{
    private EmailTemplateRepository $repository;

    public function test_get_all_by_site_filters_and_sorts_templates(): void
    {
        EmailTemplate::create([
            'site_id' => $this->siteId,
            'name' => 'Bravo',
            'slug' => 'bravo',
            'category' => 'marketing',
            'blocks' => [],
            'is_active' => true,
        ]);
        EmailTemplate::create([
            'site_id' => $this->siteId,
            'name' => 'Alpha',
            'slug' => 'alpha',
            'category' => 'marketing',
            'blocks' => [],
            'is_active' => true,
        ]);
        $otherSite = $this->createSite(['slug' => 'other-site-' . uniqid(), 'is_default' => false]);

        EmailTemplate::create([
            'site_id' => $otherSite->id,
            'name' => 'Other Site',
            'slug' => 'other-site',
            'category' => 'marketing',
            'blocks' => [],
            'is_active' => true,
        ]);

        $result = $this->repository->getAllBySite($this->siteId, 'marketing');

        $this->assertCount(2, $result);
        $names = $result->map(fn(EmailTemplate $template) => $template->name)->toArray();
        $this->assertSame(['Alpha', 'Bravo'], $names);
    }

    public function test_slug_exists_for_site_honours_excluded_id(): void
    {
        $template = EmailTemplate::create([
            'site_id' => $this->siteId,
            'name' => 'Welcome',
            'slug' => 'welcome',
            'category' => 'transactional',
            'blocks' => [],
            'is_active' => true,
        ]);

        $this->assertTrue($this->repository->slugExistsForSite('welcome', $this->siteId));
        $this->assertFalse($this->repository->slugExistsForSite('welcome', $this->siteId, $template->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EmailTemplateRepository();
    }
}
