<?php

namespace App\Tests\Unit\Models;

use App\Models\ConsentType;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberConsentModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateMemberConsent()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $consent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web',
            'ip_address' => '192.168.1.1',
            'granted_at' => now_datetime()
        ]);

        $this->assertInstanceOf(MemberConsent::class, $consent);
        $this->assertTrue($consent->is_granted);
        $this->assertEquals('web', $consent->channel);
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

    public function testIsActive()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $active = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        $revoked = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'another'])->id,
            'is_granted' => false,
            'channel' => 'web',
            'revoked_at' => now_datetime()
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($revoked->isActive());
    }

    public function testIsExpired()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $expired = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()->modify('-2 days'),
            'expires_at' => now_datetime()->modify('-1 day')
        ]);

        $notExpired = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'another'])->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime(),
            'expires_at' => now_datetime()->modify('+30 days')
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($notExpired->isExpired());
    }

    public function testScopeGranted()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web'
        ]);

        MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'another'])->id,
            'is_granted' => false,
            'channel' => 'web'
        ]);

        $granted = MemberConsent::granted()->get();
        $this->assertCount(1, $granted);
    }

    public function testScopeActive()
    {
        $member = $this->createMember();

        MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'active'])->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'revoked'])->id,
            'is_granted' => false,
            'channel' => 'web',
            'revoked_at' => now_datetime()
        ]);

        $active = MemberConsent::active()->get();
        $this->assertCount(1, $active);
    }

    public function testScopeExpired()
    {
        $member = $this->createMember();

        MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'expired'])->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()->modify('-2 days'),
            'expires_at' => now_datetime()->modify('-1 day')
        ]);

        MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'valid'])->id,
            'is_granted' => true,
            'channel' => 'web',
            'granted_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $expired = MemberConsent::expired()->get();
        $this->assertCount(1, $expired);
    }

    public function testMetadataCast()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $consent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web',
            'metadata' => ['key1' => 'value1', 'key2' => 'value2']
        ]);

        $this->assertIsArray($consent->metadata);
        $this->assertEquals('value1', $consent->metadata['key1']);
    }

    public function testRelationships()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $consent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web'
        ]);

        $this->assertInstanceOf(Member::class, $consent->member());
        $this->assertInstanceOf(ConsentType::class, $consent->consentType());
        $this->assertEquals($member->id, $consent->member()->id);
        $this->assertEquals($consentType->id, $consent->consentType()->id);
    }

    public function testChannelAttribute()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $webConsent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web'
        ]);

        $emailConsent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $this->createConsentType(['code' => 'email_consent'])->id,
            'is_granted' => true,
            'channel' => 'email'
        ]);

        $this->assertEquals('web', $webConsent->channel);
        $this->assertEquals('email', $emailConsent->channel);
    }

    public function testTimestamps()
    {
        $member = $this->createMember();
        $consentType = $this->createConsentType();

        $consent = MemberConsent::create([
            'member_id' => $member->id,
            'consent_type_id' => $consentType->id,
            'is_granted' => true,
            'channel' => 'web'
        ]);

        $this->assertNotNull($consent->created_at);
        $this->assertNotNull($consent->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}