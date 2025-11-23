<?php

namespace App\Tests\Unit\Models;

use App\Models\ConsentType;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ConsentTypeModelTest extends FunctionalTestCase
{
    public function testCreateConsentType()
    {
        $consentType = ConsentType::create([
            'code' => 'test_consent',
            'name' => 'Test Consent',
            'description' => 'Test description',
            'category' => 'marketing',
            'required' => false,
            'retention_days' => 365,
            'data_purposes' => ['purpose1', 'purpose2'],
            'is_active' => true
        ]);

        $this->assertInstanceOf(ConsentType::class, $consentType);
        $this->assertEquals('test_consent', $consentType->code);
        $this->assertEquals('marketing', $consentType->category);
    }

    public function testIsRequired()
    {
        $required = ConsentType::create([
            'code' => 'essential',
            'name' => 'Essential',
            'description' => 'Essential consent',
            'category' => 'essential',
            'required' => true,
            'data_purposes' => [],
            'is_active' => true
        ]);

        $optional = ConsentType::create([
            'code' => 'optional',
            'name' => 'Optional',
            'description' => 'Optional consent',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => [],
            'is_active' => true
        ]);

        $this->assertTrue($required->isRequired());
        $this->assertFalse($optional->isRequired());
    }

    public function testIsActive()
    {
        $active = ConsentType::create([
            'code' => 'active',
            'name' => 'Active',
            'description' => 'Active consent',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => [],
            'is_active' => true
        ]);

        $inactive = ConsentType::create([
            'code' => 'inactive',
            'name' => 'Inactive',
            'description' => 'Inactive consent',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => [],
            'is_active' => false
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function testScopeActive()
    {
        ConsentType::create(['code' => 'active1', 'name' => 'Active 1', 'description' => 'Test', 'category' => 'marketing', 'data_purposes' => [], 'is_active' => true]);
        ConsentType::create(['code' => 'inactive1', 'name' => 'Inactive 1', 'description' => 'Test', 'category' => 'marketing', 'data_purposes' => [], 'is_active' => false]);

        $active = ConsentType::active()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('active1', $active->first()->code);
    }

    public function testScopeByCode()
    {
        ConsentType::create(['code' => 'marketing_email', 'name' => 'Marketing Email', 'description' => 'Test', 'category' => 'marketing', 'data_purposes' => [], 'is_active' => true]);
        ConsentType::create(['code' => 'analytics', 'name' => 'Analytics', 'description' => 'Test', 'category' => 'analytics', 'data_purposes' => [], 'is_active' => true]);

        $consent = ConsentType::byCode('marketing_email')->first();
        $this->assertEquals('Marketing Email', $consent->name);
    }

    public function testScopeByCategory()
    {
        ConsentType::create(['code' => 'marketing1', 'name' => 'Marketing 1', 'description' => 'Test', 'category' => 'marketing', 'data_purposes' => [], 'is_active' => true]);
        ConsentType::create(['code' => 'marketing2', 'name' => 'Marketing 2', 'description' => 'Test', 'category' => 'marketing', 'data_purposes' => [], 'is_active' => true]);
        ConsentType::create(['code' => 'analytics1', 'name' => 'Analytics 1', 'description' => 'Test', 'category' => 'analytics', 'data_purposes' => [], 'is_active' => true]);

        $marketing = ConsentType::byCategory('marketing')->get();
        $this->assertCount(2, $marketing);
    }

    public function testScopeRequired()
    {
        ConsentType::create(['code' => 'req1', 'name' => 'Required 1', 'description' => 'Test', 'category' => 'essential', 'required' => true, 'data_purposes' => [], 'is_active' => true]);
        ConsentType::create(['code' => 'opt1', 'name' => 'Optional 1', 'description' => 'Test', 'category' => 'marketing', 'required' => false, 'data_purposes' => [], 'is_active' => true]);

        $required = ConsentType::required()->get();
        $this->assertCount(1, $required);
        $this->assertTrue($required->first()->required);
    }

    public function testScopeOptional()
    {
        ConsentType::create(['code' => 'req1', 'name' => 'Required 1', 'description' => 'Test', 'category' => 'essential', 'required' => true, 'data_purposes' => [], 'is_active' => true]);
        ConsentType::create(['code' => 'opt1', 'name' => 'Optional 1', 'description' => 'Test', 'category' => 'marketing', 'required' => false, 'data_purposes' => [], 'is_active' => true]);

        $optional = ConsentType::optional()->get();
        $this->assertCount(1, $optional);
        $this->assertFalse($optional->first()->required);
    }

    public function testDataPurposesCast()
    {
        $consentType = ConsentType::create([
            'code' => 'test',
            'name' => 'Test',
            'description' => 'Test',
            'category' => 'marketing',
            'data_purposes' => ['purpose1', 'purpose2', 'purpose3'],
            'is_active' => true
        ]);

        $this->assertIsArray($consentType->data_purposes);
        $this->assertCount(3, $consentType->data_purposes);
        $this->assertEquals('purpose1', $consentType->data_purposes[0]);
    }

    public function testRetentionDays()
    {
        $withRetention = ConsentType::create([
            'code' => 'retention',
            'name' => 'With Retention',
            'description' => 'Test',
            'category' => 'marketing',
            'retention_days' => 365,
            'data_purposes' => [],
            'is_active' => true
        ]);

        $noRetention = ConsentType::create([
            'code' => 'no_retention',
            'name' => 'No Retention',
            'description' => 'Test',
            'category' => 'essential',
            'retention_days' => null,
            'data_purposes' => [],
            'is_active' => true
        ]);

        $this->assertEquals(365, $withRetention->retention_days);
        $this->assertNull($noRetention->retention_days);
    }

    public function testUpdateConsentType()
    {
        $consentType = ConsentType::create([
            'code' => 'original',
            'name' => 'Original Name',
            'description' => 'Original description',
            'category' => 'marketing',
            'data_purposes' => [],
            'is_active' => true
        ]);

        $consentType->update([
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ]);

        $fresh = ConsentType::find($consentType->id);
        $this->assertEquals('Updated Name', $fresh->name);
        $this->assertEquals('Updated description', $fresh->description);
    }

    public function testDeleteConsentType()
    {
        $consentType = ConsentType::create([
            'code' => 'to_delete',
            'name' => 'To Delete',
            'description' => 'Test',
            'category' => 'marketing',
            'data_purposes' => [],
            'is_active' => true
        ]);

        $id = $consentType->id;
        $consentType->delete();

        $deleted = ConsentType::find($id);
        $this->assertNull($deleted);
    }

    public function testTimestamps()
    {
        $consentType = ConsentType::create([
            'code' => 'test',
            'name' => 'Test',
            'description' => 'Test',
            'category' => 'marketing',
            'data_purposes' => [],
            'is_active' => true
        ]);

        $this->assertNotNull($consentType->created_at);
        $this->assertNotNull($consentType->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}