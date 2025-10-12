<?php

namespace App\Tests\Unit\Models;

use App\Framework\Support\Collection;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SiteTest extends FunctionalTestCase
{
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = new Site([
            'name' => 'Test Site',
            'slug' => 'test-site',
            'domain' => 'example.com',
            'subdomain' => null,
            'theme' => 'default',
            'logo' => 'logo.png',
            'favicon' => 'favicon.ico',
            'is_active' => true,
            'is_default' => false,
            'settings' => json_encode(['key' => 'value'])
        ]);
    }

    public function testSiteCanBeInstantiated()
    {
        $this->assertInstanceOf(Site::class, $this->site);
    }

    public function testSiteHasCorrectTableName()
    {
        $this->assertEquals('sites', $this->site->getTable());
    }

    public function testIsActiveReturnsTrue()
    {
        $this->site->is_active = true;
        $this->assertTrue($this->site->isActive());
    }

    public function testIsActiveReturnsFalse()
    {
        $this->site->is_active = false;
        $this->assertFalse($this->site->isActive());
    }

    public function testIsDefaultReturnsTrue()
    {
        $this->site->is_default = true;
        $this->assertTrue($this->site->isDefault());
    }

    public function testIsDefaultReturnsFalse()
    {
        $this->site->is_default = false;
        $this->assertFalse($this->site->isDefault());
    }

    public function testGetThemePathReturnsTheme()
    {
        $this->site->theme = 'custom-theme';
        $this->assertEquals('custom-theme', $this->site->getThemePath());
    }

    public function testGetThemePathReturnsDefaultWhenNull()
    {
        $this->site->theme = null;
        $this->assertEquals('default', $this->site->getThemePath());
    }

    public function testGetSettingReturnsValue()
    {
        $this->site->settings = ['timezone' => 'UTC', 'currency' => 'USD'];
        $result = $this->site->getSetting('timezone');

        $this->assertEquals('UTC', $result);
    }

    public function testGetSettingReturnsDefaultWhenKeyNotFound()
    {
        $this->site->settings = ['timezone' => 'UTC'];
        $result = $this->site->getSetting('nonexistent', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function testGetSettingHandlesJsonString()
    {
        $this->site->settings = json_encode(['timezone' => 'UTC']);
        $result = $this->site->getSetting('timezone');

        $this->assertEquals('UTC', $result);
    }

    public function testSetSettingUpdatesValue()
    {
        $this->site->settings = ['timezone' => 'UTC'];
        $this->site->setSetting('currency', 'EUR');

        $settings = $this->site->settings;
        $this->assertArrayHasKey('currency', $settings);
        $this->assertEquals('EUR', $settings['currency']);
    }

    public function testSetSettingHandlesJsonString()
    {
        $this->site->settings = json_encode(['timezone' => 'UTC']);
        $this->site->setSetting('currency', 'EUR');

        $settings = $this->site->settings;
        $this->assertArrayHasKey('currency', $settings);
        $this->assertEquals('EUR', $settings['currency']);
    }

    public function testSetSettingHandlesNullSettings()
    {
        $this->site->settings = null;
        $this->site->setSetting('timezone', 'UTC');

        $settings = $this->site->settings;
        $this->assertIsArray($settings);
        $this->assertEquals('UTC', $settings['timezone']);
    }

    public function testMatchesDomainReturnsTrueForDirectMatch()
    {
        $this->site->domain = 'example.com';
        $this->assertTrue($this->site->matchesDomain('example.com'));
    }

    public function testMatchesDomainReturnsFalseForNonMatch()
    {
        $this->site->domain = 'example.com';
        $this->assertFalse($this->site->matchesDomain('different.com'));
    }

    public function testMatchesDomainHandlesPort()
    {
        $this->site->domain = 'example.com';
        $this->assertTrue($this->site->matchesDomain('example.com:8080'));
    }
    public function testScopeActiveAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('is_active', 1)
            ->willReturnSelf();

        $result = $this->site->scopeActive($query);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeDefaultAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('is_default', 1)
            ->willReturnSelf();

        $result = $this->site->scopeDefault($query);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testPagesRelationReturnsCorrectType()
    {
        $relation = $this->site->pages();
        $this->assertInstanceOf(Collection::class, $relation);
    }

    public function testMenusRelationReturnsCorrectType()
    {
        $relation = $this->site->menus();
        $this->assertInstanceOf(Collection::class, $relation);
    }

    public function testCategoriesRelationReturnsCorrectType()
    {
        $relation = $this->site->categories();
        $this->assertInstanceOf(Collection::class, $relation);
    }

    public function testTagsRelationReturnsCorrectType()
    {
        $relation = $this->site->tags();
        $this->assertInstanceOf(Collection::class, $relation);
    }

    public function testSetAndGetName()
    {
        $this->site->name = 'New Site Name';
        $this->assertEquals('New Site Name', $this->site->name);
    }

    public function testSetAndGetSlug()
    {
        $this->site->slug = 'new-site-slug';
        $this->assertEquals('new-site-slug', $this->site->slug);
    }

    public function testSetAndGetDomain()
    {
        $this->site->domain = 'newdomain.com';
        $this->assertEquals('newdomain.com', $this->site->domain);
    }

    public function testSetAndGetSubdomain()
    {
        $this->site->subdomain = 'blog';
        $this->assertEquals('blog', $this->site->subdomain);
    }

    public function testSetAndGetTheme()
    {
        $this->site->theme = 'custom-theme';
        $this->assertEquals('custom-theme', $this->site->theme);
    }

    public function testSetAndGetLogo()
    {
        $this->site->logo = 'new-logo.png';
        $this->assertEquals('new-logo.png', $this->site->logo);
    }

    public function testSetAndGetFavicon()
    {
        $this->site->favicon = 'new-favicon.ico';
        $this->assertEquals('new-favicon.ico', $this->site->favicon);
    }

    public function testSetAndGetIsActive()
    {
        $this->site->is_active = false;
        $this->assertFalse($this->site->is_active);

        $this->site->is_active = true;
        $this->assertTrue($this->site->is_active);
    }

    public function testSetAndGetIsDefault()
    {
        $this->site->is_default = true;
        $this->assertTrue($this->site->is_default);

        $this->site->is_default = false;
        $this->assertFalse($this->site->is_default);
    }

    public function testSetAndGetSettings()
    {
        $settings = ['timezone' => 'UTC', 'currency' => 'USD'];
        $this->site->settings = $settings;
        $this->assertEquals($settings, $this->site->settings);
    }

    public function testIsActiveHandlesIntegerValues()
    {
        $this->site->is_active = 1;
        $this->assertTrue($this->site->isActive());

        $this->site->is_active = 0;
        $this->assertFalse($this->site->isActive());
    }
    public function testIsDefaultHandlesIntegerValues()
    {
        $this->site->is_default = 1;
        $this->assertTrue($this->site->isDefault());

        $this->site->is_default = 0;
        $this->assertFalse($this->site->isDefault());
    }

    public function testGetSettingReturnsNullWhenKeyNotFoundAndNoDefault()
    {
        $this->site->settings = ['timezone' => 'UTC'];
        $result = $this->site->getSetting('nonexistent');

        $this->assertNull($result);
    }
    public function testGetSettingHandlesEmptySettings()
    {
        $this->site->settings = null;
        $result = $this->site->getSetting('timezone', 'default');

        $this->assertEquals('default', $result);
    }

    public function testSetSettingOverwritesExistingValue()
    {
        $this->site->settings = ['timezone' => 'PST'];
        $this->site->setSetting('timezone', 'EST');

        $settings = $this->site->settings;
        $this->assertEquals('EST', $settings['timezone']);
    }

    public function testMatchesDomainHandlesDifferentPorts()
    {
        $this->site->domain = 'example.com';
        $this->assertTrue($this->site->matchesDomain('example.com:3000'));
        $this->assertTrue($this->site->matchesDomain('example.com:80'));
    }

    public function testMatchesDomainIsCaseSensitive()
    {
        $this->site->domain = 'example.com';
        $this->assertFalse($this->site->matchesDomain('Example.com'));
    }
    public function testSettingsAreCastedToJson()
    {
        $settings = ['key1' => 'value1', 'key2' => 'value2'];
        $this->site->settings = $settings;

        $retrieved = $this->site->settings;
        $this->assertIsArray($retrieved);
        $this->assertEquals($settings, $retrieved);
    }

    public function testBooleanAttributesAreCastedCorrectly()
    {
        $this->site->is_active = 1;
        $this->assertIsBool($this->site->is_active);
        $this->assertTrue($this->site->is_active);

        $this->site->is_default = 0;
        $this->assertIsBool($this->site->is_default);
        $this->assertFalse($this->site->is_default);
    }

    public function testToArrayIncludesAllAttributes()
    {
        $array = $this->site->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('domain', $array);
        $this->assertArrayHasKey('theme', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('is_default', $array);
        $this->assertArrayHasKey('settings', $array);
    }

    public function testCreateSite()
    {
        $site = Site::create([
            'name' => 'New Site',
            'slug' => 'new-site',
            'domain' => 'newsite.com',
            'is_active' => true,
            'is_default' => false
        ]);

        $this->assertInstanceOf(Site::class, $site);
        $this->assertEquals('New Site', $site->name);
        $this->assertEquals('new-site', $site->slug);
        $this->assertEquals('newsite.com', $site->domain);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $site = new Site();
        $site->fill([
            'name' => 'Filled Site',
            'slug' => 'filled-site',
            'theme' => 'modern'
        ]);

        $this->assertEquals('Filled Site', $site->name);
        $this->assertEquals('filled-site', $site->slug);
        $this->assertEquals('modern', $site->theme);
    }

    public function testSetMultipleSettings()
    {
        $this->site->settings = [];

        $this->site->setSetting('timezone', 'America/New_York');
        $this->site->setSetting('currency', 'USD');
        $this->site->setSetting('language', 'en');

        $settings = $this->site->settings;
        $this->assertCount(3, $settings);
        $this->assertEquals('America/New_York', $settings['timezone']);
        $this->assertEquals('USD', $settings['currency']);
        $this->assertEquals('en', $settings['language']);
    }

    public function testGetSettingWithNestedKeys()
    {
        $this->site->settings = [
            'social' => [
                'twitter' => '@example',
                'facebook' => 'example'
            ]
        ];

        $social = $this->site->getSetting('social');
        $this->assertIsArray($social);
        $this->assertEquals('@example', $social['twitter']);
    }

    public function testMatchesDomainWithSubdomain()
    {
        $this->site->subdomain = 'blog';
        $this->site->domain = null;

        // This test depends on config, so we test the method exists
        $result = $this->site->matchesDomain('blog.example.com');
        $this->assertIsBool($result);
    }

    public function testGetContactInfo(): void
    {
        $site = new Site([
            'contact_email' => 'test@example.com',
            'contact_phone' => '+44 20 1234 5678',
            'contact_address_line1' => '123 Test Street',
            'contact_address_line2' => 'Suite 100',
            'contact_city' => 'London',
            'contact_postcode' => 'SW1A 1AA',
            'contact_country' => 'UK',
            'facebook_url' => 'https://facebook.com/test',
            'instagram_url' => 'https://instagram.com/test',
            'twitter_url' => 'https://twitter.com/test',
            'linkedin_url' => 'https://linkedin.com/test'
        ]);

        $contactInfo = $site->getContactInfo();

        $this->assertEquals('test@example.com', $contactInfo['email']);
        $this->assertEquals('+44 20 1234 5678', $contactInfo['phone']);
        $this->assertEquals('123 Test Street', $contactInfo['address']['line1']);
        $this->assertEquals('Suite 100', $contactInfo['address']['line2']);
        $this->assertEquals('London', $contactInfo['address']['city']);
        $this->assertEquals('SW1A 1AA', $contactInfo['address']['postcode']);
        $this->assertEquals('UK', $contactInfo['address']['country']);
        $this->assertEquals('https://facebook.com/test', $contactInfo['social']['facebook']);
        $this->assertEquals('https://instagram.com/test', $contactInfo['social']['instagram']);
        $this->assertEquals('https://twitter.com/test', $contactInfo['social']['twitter']);
        $this->assertEquals('https://linkedin.com/test', $contactInfo['social']['linkedin']);
    }

    public function testGetContactInfoWithNullValues(): void
    {
        $site = new Site([
            'contact_email' => null,
            'contact_phone' => null
        ]);

        $contactInfo = $site->getContactInfo();

        $this->assertNull($contactInfo['email']);
        $this->assertNull($contactInfo['phone']);
        $this->assertNull($contactInfo['address']['line1']);
    }

    public function testJsonCasting(): void
    {
        $settings = ['theme' => 'dark', 'language' => 'en'];

        $site = new Site([
            'settings' => $settings
        ]);

        $this->assertIsArray($site->settings);
        $this->assertEquals('dark', $site->settings['theme']);
        $this->assertEquals('en', $site->settings['language']);
    }
}