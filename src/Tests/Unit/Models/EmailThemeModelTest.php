<?php
// tests/Unit/Models/EmailThemeModelTest.php

namespace App\Tests\Unit\Models;

use App\Models\EmailTheme;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class EmailThemeModelTest extends FunctionalTestCase
{
    public function testCreateEmailTheme()
    {
        $theme = EmailTheme::create([
            'name' => 'Modern Theme',
            'slug' => 'modern-theme',
            'description' => 'A modern email theme',
            'is_active' => true,
            'is_default' => false,
            'site_id' => $this->siteId
        ]);

        $this->assertInstanceOf(EmailTheme::class, $theme);
        $this->assertEquals('Modern Theme', $theme->name);
        $this->assertEquals(1, $theme->site_id);
    }

    public function testScopeActive()
    {
        EmailTheme::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true, 'site_id' => $this->siteId]);
        EmailTheme::create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false, 'site_id' => $this->siteId]);

        $active = EmailTheme::active()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->name);
    }

    public function testScopeDefault()
    {
        EmailTheme::create(['name' => 'Default', 'slug' => 'default', 'is_default' => true, 'site_id' => $this->siteId, 'is_active' => true]);
        EmailTheme::create(['name' => 'Other', 'slug' => 'other', 'is_default' => false, 'site_id' => $this->siteId, 'is_active' => true]);

        $default = EmailTheme::default()->first();
        $this->assertEquals('Default', $default->name);
    }

    public function testScopeBySite()
    {
        $site2 = Site::create(['name' => 'Site 2', 'domain' => 'site2.com']);

        EmailTheme::create(['name' => 'Site 1', 'slug' => 'site-1', 'site_id' => $this->siteId, 'is_active' => true]);
        EmailTheme::create(['name' => 'Site 2', 'slug' => 'site-2', 'site_id' => $site2->id, 'is_active' => true]);
        $site1Themes = EmailTheme::bySite(1)->get();
        $this->assertCount(1, $site1Themes);
        $this->assertEquals('Site 1', $site1Themes->first()->name);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}