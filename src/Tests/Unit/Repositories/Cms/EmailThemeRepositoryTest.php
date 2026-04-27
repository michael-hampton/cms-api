<?php
// tests/Unit/Repositories/EmailThemeRepositoryTest.php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\EmailTheme;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\EmailThemeRepository;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EmailThemeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private EmailThemeRepository $repository;

    public function test_it_can_find_theme_by_slug_and_site(): void
    {
        $theme = NewsletterBrandingConfiguration::create([
            'name' => 'Test Theme',
            'slug' => 'test-theme',
            'site_id' => $this->siteId,
            'is_active' => true,
            'type' => 'email_template'
        ]);

        $found = $this->repository->findBySlug('test-theme', $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals($theme->id, $found->id);
    }

    public function test_get_default_for_site(): void
    {
        $default = NewsletterBrandingConfiguration::create([
            'name' => 'Default',
            'slug' => 'default',
            'site_id' => $this->siteId,
            'is_default' => true,
            'is_active' => true,
            'type' => 'email_template'
        ]);

        $found = $this->repository->getDefaultForSite($this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals($default->id, $found->id);
        $this->assertTrue($found->is_default);
    }

    public function test_get_active_by_site(): void
    {
        NewsletterBrandingConfiguration::create(['name' => 'Active 1', 'slug' => 'active-1', 'site_id' => $this->siteId, 'is_active' => true, 'type' => 'email_template']);
        NewsletterBrandingConfiguration::create(['name' => 'Active 2', 'slug' => 'active-2', 'site_id' => $this->siteId, 'is_active' => true, 'type' => 'email_template']);
        NewsletterBrandingConfiguration::create(['name' => 'Inactive', 'slug' => 'inactive', 'site_id' => $this->siteId, 'is_active' => false, 'type' => 'email_template']);

        $active = $this->repository->getActiveBySite($this->siteId);

        $this->assertCount(2, $active);
    }

    public function test_set_default_theme(): void
    {
        $theme1 = NewsletterBrandingConfiguration::create(['name' => 'Theme 1', 'slug' => 'theme-1', 'site_id' => $this->siteId, 'is_default' => true, 'is_active' => true, 'type' => 'email_template']);
        $theme2 = NewsletterBrandingConfiguration::create(['name' => 'Theme 2', 'slug' => 'theme-2', 'site_id' => $this->siteId, 'is_default' => false, 'is_active' => true, 'type' => 'email_template']);

        $result = $this->repository->setDefaultTheme($theme2->id, $this->siteId);

        $this->assertTrue($result);

        $theme1 = $theme1->fresh();
        $theme2 = $theme2->fresh();

        $this->assertFalse($theme1->is_default);
        $this->assertTrue($theme2->is_default);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EmailThemeRepository(app(NewsletterBrandingRepository::class));
    }

//    public function test_search_returns_paginated_results(): void
//    {
//        EmailTheme::create(['name' => 'Theme 1', 'slug' => 'theme-1', 'site_id' => $this->siteId, 'is_active' => true]);
//        EmailTheme::create(['name' => 'Theme 2', 'slug' => 'theme-2', 'site_id' => $this->siteId, 'is_active' => true]);
//
//        $criteria = new SearchCriteria();
//        $criteria->setPerPage(10);
//        $criteria->setSiteId($this->siteId);
//
//        $result = $this->repository->search($criteria);
//
//        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
//        $this->assertCount(2, $result->getData());
//    }
}