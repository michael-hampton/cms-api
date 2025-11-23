<?php

namespace App\Tests\Unit\Models;

use App\Models\DataProcessingActivity;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class DataProcessingActivityModelTest extends FunctionalTestCase
{
    public function testCreateDataProcessingActivity()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'User Account Management',
            'purpose' => 'Process user registration and authentication',
            'data_categories' => ['name', 'email', 'password_hash'],
            'data_subjects' => ['customers', 'website_users'],
            'recipients' => ['internal_staff', 'hosting_provider'],
            'transfers' => null,
            'retention_period_days' => 2555,
            'security_measures' => ['encryption_at_rest', 'encryption_in_transit'],
            'related_consent_types' => ['account_management']
        ]);

        $this->assertInstanceOf(DataProcessingActivity::class, $activity);
        $this->assertEquals('User Account Management', $activity->name);
        $this->assertEquals(2555, $activity->retention_period_days);
    }

    public function testDataCategoriesCast()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Analytics',
            'purpose' => 'Track website usage',
            'data_categories' => ['ip_address', 'browser_info', 'page_views'],
            'data_subjects' => ['website_visitors'],
            'recipients' => ['internal_analytics'],
            'retention_period_days' => 730,
            'security_measures' => ['pseudonymization'],
            'related_consent_types' => ['analytics']
        ]);

        $this->assertIsArray($activity->data_categories);
        $this->assertCount(3, $activity->data_categories);
        $this->assertContains('ip_address', $activity->data_categories);
    }

    public function testDataSubjectsCast()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Marketing',
            'purpose' => 'Send promotional emails',
            'data_categories' => ['email', 'name'],
            'data_subjects' => ['customers', 'subscribers', 'leads'],
            'recipients' => ['email_service_provider'],
            'retention_period_days' => 1095,
            'security_measures' => ['encryption_in_transit'],
            'related_consent_types' => ['marketing_email']
        ]);

        $this->assertIsArray($activity->data_subjects);
        $this->assertCount(3, $activity->data_subjects);
        $this->assertContains('subscribers', $activity->data_subjects);
    }

    public function testRecipientsCast()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Payment Processing',
            'purpose' => 'Process customer payments',
            'data_categories' => ['payment_info'],
            'data_subjects' => ['customers'],
            'recipients' => ['payment_processor', 'bank', 'fraud_detection_service'],
            'retention_period_days' => 2555,
            'security_measures' => ['pci_dss_compliance'],
            'related_consent_types' => ['account_management']
        ]);

        $this->assertIsArray($activity->recipients);
        $this->assertCount(3, $activity->recipients);
        $this->assertContains('payment_processor', $activity->recipients);
    }

    public function testTransfersCast()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Cloud Storage',
            'purpose' => 'Store user files',
            'data_categories' => ['files', 'metadata'],
            'data_subjects' => ['users'],
            'recipients' => ['cloud_provider'],
            'transfers' => [
                'US' => 'Standard Contractual Clauses',
                'EU' => 'Adequacy Decision'
            ],
            'retention_period_days' => 365,
            'security_measures' => ['encryption_at_rest'],
            'related_consent_types' => ['account_management']
        ]);

        $this->assertIsArray($activity->transfers);
        $this->assertArrayHasKey('US', $activity->transfers);
        $this->assertEquals('Standard Contractual Clauses', $activity->transfers['US']);
    }

    public function testSecurityMeasuresCast()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Database Management',
            'purpose' => 'Store application data',
            'data_categories' => ['all_user_data'],
            'data_subjects' => ['users'],
            'recipients' => ['internal_staff'],
            'retention_period_days' => 3650,
            'security_measures' => [
                'encryption_at_rest',
                'encryption_in_transit',
                'access_controls',
                'regular_backups',
                'intrusion_detection'
            ],
            'related_consent_types' => ['account_management']
        ]);

        $this->assertIsArray($activity->security_measures);
        $this->assertCount(5, $activity->security_measures);
        $this->assertContains('regular_backups', $activity->security_measures);
    }

    public function testRelatedConsentTypesCast()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Marketing Campaign',
            'purpose' => 'Run targeted marketing',
            'data_categories' => ['email', 'preferences'],
            'data_subjects' => ['customers'],
            'recipients' => ['marketing_team', 'email_provider'],
            'retention_period_days' => 1095,
            'security_measures' => ['encryption_in_transit'],
            'related_consent_types' => ['marketing_email', 'profiling', 'targeted_advertising']
        ]);

        $this->assertIsArray($activity->related_consent_types);
        $this->assertCount(3, $activity->related_consent_types);
        $this->assertContains('marketing_email', $activity->related_consent_types);
    }

    public function testHasInternationalTransfers()
    {
        $withTransfers = DataProcessingActivity::create([
            'name' => 'International Service',
            'purpose' => 'Global data processing',
            'data_categories' => ['user_data'],
            'data_subjects' => ['users'],
            'recipients' => ['global_partners'],
            'transfers' => ['US' => 'SCCs', 'Asia' => 'BCRs'],
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['account_management']
        ]);

        $withoutTransfers = DataProcessingActivity::create([
            'name' => 'Local Service',
            'purpose' => 'Local data processing',
            'data_categories' => ['user_data'],
            'data_subjects' => ['users'],
            'recipients' => ['local_staff'],
            'transfers' => null,
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['account_management']
        ]);

        $this->assertTrue($withTransfers->hasInternationalTransfers());
        $this->assertFalse($withoutTransfers->hasInternationalTransfers());
    }

    public function testGetRetentionPeriodYears()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Test Activity',
            'purpose' => 'Testing',
            'data_categories' => ['test'],
            'data_subjects' => ['users'],
            'recipients' => ['internal'],
            'retention_period_days' => 730, // 2 years
            'security_measures' => ['encryption'],
            'related_consent_types' => ['test']
        ]);

        $this->assertEquals(2.0, $activity->getRetentionPeriodYears());
    }

    public function testInvolvesConsentType()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Multi-purpose Activity',
            'purpose' => 'Various processing',
            'data_categories' => ['user_data'],
            'data_subjects' => ['users'],
            'recipients' => ['internal'],
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['marketing_email', 'analytics', 'profiling']
        ]);

        $this->assertTrue($activity->involvesConsentType('marketing_email'));
        $this->assertTrue($activity->involvesConsentType('analytics'));
        $this->assertFalse($activity->involvesConsentType('targeted_advertising'));
    }

    public function testScopeByConsentType()
    {
        DataProcessingActivity::create([
            'name' => 'Marketing Activity',
            'purpose' => 'Marketing',
            'data_categories' => ['email'],
            'data_subjects' => ['customers'],
            'recipients' => ['marketing_team'],
            'retention_period_days' => 1095,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['marketing_email', 'profiling']
        ]);

        DataProcessingActivity::create([
            'name' => 'Analytics Activity',
            'purpose' => 'Analytics',
            'data_categories' => ['usage_data'],
            'data_subjects' => ['visitors'],
            'recipients' => ['analytics_team'],
            'retention_period_days' => 730,
            'security_measures' => ['pseudonymization'],
            'related_consent_types' => ['analytics']
        ]);

        $marketingActivities = DataProcessingActivity::byConsentType('marketing_email')->get();
        $this->assertCount(1, $marketingActivities);
        $this->assertEquals('Marketing Activity', $marketingActivities->first()->name);
    }

    public function testScopeWithTransfers()
    {
        DataProcessingActivity::create([
            'name' => 'International Activity',
            'purpose' => 'Global processing',
            'data_categories' => ['user_data'],
            'data_subjects' => ['users'],
            'recipients' => ['global_partners'],
            'transfers' => ['US' => 'SCCs'],
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['account_management']
        ]);

        DataProcessingActivity::create([
            'name' => 'Local Activity',
            'purpose' => 'Local processing',
            'data_categories' => ['user_data'],
            'data_subjects' => ['users'],
            'recipients' => ['local_staff'],
            'transfers' => null,
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['account_management']
        ]);

        $withTransfers = DataProcessingActivity::withTransfers()->get();
        $this->assertCount(1, $withTransfers);
        $this->assertEquals('International Activity', $withTransfers->first()->name);
    }

    public function testScopeByRecipient()
    {
        DataProcessingActivity::create([
            'name' => 'Email Processing',
            'purpose' => 'Send emails',
            'data_categories' => ['email'],
            'data_subjects' => ['users'],
            'recipients' => ['email_service_provider', 'internal_marketing'],
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['marketing_email']
        ]);

        DataProcessingActivity::create([
            'name' => 'Payment Processing',
            'purpose' => 'Process payments',
            'data_categories' => ['payment_info'],
            'data_subjects' => ['customers'],
            'recipients' => ['payment_processor', 'bank'],
            'retention_period_days' => 2555,
            'security_measures' => ['pci_dss'],
            'related_consent_types' => ['account_management']
        ]);

        $emailActivities = DataProcessingActivity::byRecipient('email_service_provider')->get();
        $this->assertCount(1, $emailActivities);
        $this->assertEquals('Email Processing', $emailActivities->first()->name);
    }

    public function testUpdateActivity()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Original Activity',
            'purpose' => 'Original purpose',
            'data_categories' => ['data1'],
            'data_subjects' => ['users'],
            'recipients' => ['recipient1'],
            'retention_period_days' => 365,
            'security_measures' => ['measure1'],
            'related_consent_types' => ['consent1']
        ]);

        $activity->update([
            'name' => 'Updated Activity',
            'purpose' => 'Updated purpose',
            'retention_period_days' => 730
        ]);

        $fresh = DataProcessingActivity::find($activity->id);
        $this->assertEquals('Updated Activity', $fresh->name);
        $this->assertEquals('Updated purpose', $fresh->purpose);
        $this->assertEquals(730, $fresh->retention_period_days);
    }

    public function testDeleteActivity()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'To Delete',
            'purpose' => 'Test deletion',
            'data_categories' => ['data'],
            'data_subjects' => ['users'],
            'recipients' => ['recipient'],
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['test']
        ]);

        $id = $activity->id;
        $activity->delete();

        $deleted = DataProcessingActivity::find($id);
        $this->assertNull($deleted);
    }

    public function testTimestamps()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Test Activity',
            'purpose' => 'Testing timestamps',
            'data_categories' => ['data'],
            'data_subjects' => ['users'],
            'recipients' => ['recipient'],
            'retention_period_days' => 365,
            'security_measures' => ['encryption'],
            'related_consent_types' => ['test']
        ]);

        $this->assertNotNull($activity->created_at);
        $this->assertNotNull($activity->updated_at);
    }

    public function testComplexTransfersStructure()
    {
        $activity = DataProcessingActivity::create([
            'name' => 'Global Service',
            'purpose' => 'Worldwide data processing',
            'data_categories' => ['all_user_data'],
            'data_subjects' => ['customers'],
            'recipients' => ['global_datacenter_network'],
            'transfers' => [
                'United States' => 'Standard Contractual Clauses',
                'United Kingdom' => 'Adequacy Decision',
                'Singapore' => 'Binding Corporate Rules',
                'Brazil' => 'Standard Contractual Clauses'
            ],
            'retention_period_days' => 1825,
            'security_measures' => ['end_to_end_encryption', 'zero_knowledge_architecture'],
            'related_consent_types' => ['account_management', 'functional_cookies']
        ]);

        $this->assertCount(4, $activity->transfers);
        $this->assertEquals('Adequacy Decision', $activity->transfers['United Kingdom']);
        $this->assertTrue($activity->hasInternationalTransfers());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}