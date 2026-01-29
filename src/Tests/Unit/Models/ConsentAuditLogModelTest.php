<?php

namespace App\Tests\Unit\Models;

use App\Models\ConsentAuditLog;
use App\Models\ConsentType;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ConsentAuditLogModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateAuditLog()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $log = ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'previous_state' => null,
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        $this->assertInstanceOf(ConsentAuditLog::class, $log);
        $this->assertEquals('granted', $log->action);
        $this->assertTrue($log->new_state);
    }

    private function createConsentType(array $overrides = []): ConsentType
    {
        return ConsentType::create(array_merge([
            'code' => 'test_consent_' . uniqid(),
            'name' => 'Test Consent',
            'description' => 'Test description',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => [],
            'is_active' => true
        ], $overrides));
    }

    public function testScopeByMember()
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $consentType = $this->createConsentType();

        ConsentAuditLog::create([
            'member_id' => $member1->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        ConsentAuditLog::create([
            'member_id' => $member2->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        $logs = ConsentAuditLog::byMember($member1->id)->get();
        $this->assertCount(1, $logs);
    }

    public function testScopeByConsentType()
    {
        $member = $this->createMember();
        $consentType1 = $this->createConsentType(['code' => 'type1']);
        $consentType2 = $this->createConsentType(['code' => 'type2']);

        ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType1->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType2->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        $logs = ConsentAuditLog::byConsentType($consentType1->id)->get();
        $this->assertCount(1, $logs);
    }

    public function testScopeByAction()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'revoked',
            'previous_state' => true,
            'new_state' => false,
            'source' => 'web',
            'created_at' => now()
        ]);

        $granted = ConsentAuditLog::byAction('granted')->get();
        $this->assertCount(1, $granted);
    }

    public function testScopeRecent()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'revoked',
            'previous_state' => true,
            'new_state' => false,
            'source' => 'web',
            'created_at' => now_datetime()->modify('-40 days')
        ]);

        $recent = ConsentAuditLog::recent(30)->get();
        $this->assertCount(1, $recent);
    }

    public function testMetadataCast()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $log = ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'metadata' => ['browser' => 'Chrome', 'version' => '96'],
            'created_at' => now()
        ]);

        $this->assertIsArray($log->metadata);
        $this->assertEquals('Chrome', $log->metadata['browser']);
    }

    public function testRelationships()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $log = ConsentAuditLog::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'action' => 'granted',
            'new_state' => true,
            'source' => 'web',
            'created_at' => now()
        ]);

        $this->assertInstanceOf(Member::class, $log->member());
        $this->assertInstanceOf(ConsentType::class, $log->consentType());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}