<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Subscriber;
use App\Repositories\MemberRepository;
use App\Repositories\MemberSubscriptionPreferenceRepository;
use App\Repositories\SubscriberRepository;
use App\Services\MemberSubscriptionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class MemberSubscriptionServiceTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $preferenceRepository;
    private $subscriberRepository;
    private $service;
    private $memberRepository;

    public function test_get_preferences_for_member_returns_preference(): void
    {
        $memberId = 1;
        $siteId = 1;
        $preference = Mockery::mock(MemberSubscriptionPreference::class);

        $this->preferenceRepository->shouldReceive('getOrCreateForMember')
            ->with($memberId, $siteId)
            ->once()
            ->andReturn($preference);

        $result = $this->service->getPreferencesForMember($memberId, $siteId);

        $this->assertSame($preference, $result);
    }

    public function test_update_preferences_prepares_and_updates_data(): void
    {
        $memberId = 1;
        $siteId = 1;
        $data = [
            'email_notifications' => '1',
            'newsletter_frequency' => 'daily',
            'content_types' => ['news', 'blog'],
            'category_preferences' => [1, 2, 3]
        ];

        $preference = Mockery::mock(MemberSubscriptionPreference::class);

        $this->preferenceRepository->shouldReceive('updatePreferences')
            ->with($memberId, Mockery::on(function ($prepared) {
                return $prepared['email_notifications'] === true
                    && $prepared['newsletter_frequency'] === 'daily'
                    && is_array($prepared['content_types'])
                    && is_array($prepared['category_preferences']);
            }), $siteId)
            ->once()
            ->andReturn($preference);

        $result = $this->service->updatePreferences($memberId, $data, $siteId);

        $this->assertSame($preference, $result);
    }

    public function test_update_preferences_creates_if_not_exists(): void
    {
        $memberId = 1;
        $siteId = 1;
        $data = ['email_notifications' => true];

        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();

        $this->preferenceRepository->shouldReceive('updatePreferences')
            ->once()
            ->andReturn(null);

        $this->preferenceRepository->shouldReceive('getOrCreateForMember')
            ->with($memberId, $siteId)
            ->once()
            ->andReturn($preference);

        $preference->shouldReceive('update')
            ->once()
            ->andReturn(true);

        $result = $this->service->updatePreferences($memberId, $data, $siteId);

        $this->assertSame($preference, $result);
    }

    public function test_subscribe_member_to_newsletters_creates_new_subscribers(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'test22@example.com';
        $siteId = 1;
        $newsletterTypes = ['weekly', 'monthly'];

        $this->subscriberRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['email'] === 'test22@example.com';
            }));

        $this->subscriberRepository->shouldReceive('findByEmail')
            ->with('test22@example.com', $siteId)
            ->twice()
            ->andReturn(null);

        $result = $this->service->subscribeMemberToNewsletters($member, $newsletterTypes, $siteId);

        $this->assertCount(2, $result);
    }

    public function test_subscribe_member_to_newsletters_returns_existing(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'test@example.com';
        $siteId = 1;
        $newsletterTypes = ['weekly'];

        $existingSubscriber = Mockery::mock(Subscriber::class);

        $this->subscriberRepository->shouldReceive('findByEmail')
            ->with('test@example.com', $siteId)
            ->once()
            ->andReturn($existingSubscriber);

        $result = $this->service->subscribeMemberToNewsletters($member, $newsletterTypes, $siteId);

        $this->assertCount(1, $result);
        $this->assertSame($existingSubscriber, $result[0]);
    }

    public function test_unsubscribe_by_token_calls_repository(): void
    {
        $token = 'test-token';

        $this->preferenceRepository->shouldReceive('unsubscribe')
            ->with($token)
            ->once()
            ->andReturn(true);

        $result = $this->service->unsubscribeByToken($token);

        $this->assertTrue($result);
    }

    public function test_resubscribe_by_token_calls_repository(): void
    {
        $token = 'test-token';

        $this->preferenceRepository->shouldReceive('resubscribe')
            ->with($token)
            ->once()
            ->andReturn(true);

        $result = $this->service->resubscribeByToken($token);

        $this->assertTrue($result);
    }

    public function test_get_unsubscribe_url_returns_correct_url(): void
    {
        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $preference->unsubscribe_token = 'test-token-123';

        $result = $this->service->getUnsubscribeUrl($preference);

        $this->assertStringContainsString('/member/subscriptions/unsubscribe/test-token-123', $result);
    }

    public function test_get_manage_url_returns_correct_url(): void
    {
        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $preference->unsubscribe_token = 'test-token-123';

        $result = $this->service->getManageUrl($preference);

        $this->assertStringContainsString('/member/subscriptions/manage/test-token-123', $result);
    }

    public function test_get_all_newsletters_for_member_calls_repository(): void
    {
        $email = 'test@example.com';
        $siteId = 1;
        $newsletters = new Collection([]);

        $this->subscriberRepository->shouldReceive('getNewslettersForMember')
            ->with($email, $siteId)
            ->once()
            ->andReturn($newsletters);

        $result = $this->service->getAllNewslettersForMember($email, $siteId);

        $this->assertSame($newsletters, $result);
    }

    public function test_get_subscription_summary_returns_complete_data(): void
    {
        $memberId = 1;
        $siteId = 1;

        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $preference->is_active = true;
        $preference->email_notifications = true;
        $preference->newsletter_frequency = 'weekly';
        $preference->content_types = ['news'];
        $preference->category_preferences = [1, 2];
        $preference->unsubscribe_token = 'test-token';

        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'test@example.com';
        $member->id = 1;

        $this->memberRepository->shouldReceive('find')->once()->with(1)->andReturn($member);

        $newsletters = new Collection([]);

        $this->preferenceRepository->shouldReceive('getOrCreateForMember')
            ->with($memberId, $siteId)
            ->once()
            ->andReturn($preference);

        $this->subscriberRepository->shouldReceive('getNewslettersForMember')
            ->with('test@example.com', $siteId)
            ->once()
            ->andReturn($newsletters);

        $result = $this->service->getSubscriptionSummary($memberId, $siteId);

        $this->assertArrayHasKey('preference', $result);
        $this->assertArrayHasKey('is_active', $result);
        $this->assertArrayHasKey('email_notifications', $result);
        $this->assertArrayHasKey('frequency', $result);
        $this->assertArrayHasKey('content_types', $result);
        $this->assertArrayHasKey('category_preferences', $result);
        $this->assertArrayHasKey('newsletters_count', $result);
        $this->assertArrayHasKey('unsubscribe_url', $result);
        $this->assertArrayHasKey('manage_url', $result);

        $this->assertTrue($result['is_active']);
        $this->assertEquals('weekly', $result['frequency']);
    }

    public function test_prepare_preferences_converts_boolean_strings(): void
    {
        $data = [
            'email_notifications' => '1',
            'is_active' => '0'
        ];

        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();

        $this->preferenceRepository->shouldReceive('updatePreferences')
            ->with(1, Mockery::on(function ($prepared) {
                return $prepared['email_notifications'] === true
                    && $prepared['is_active'] === false;
            }), 1)
            ->once()
            ->andReturn($preference);

        $result = $this->service->updatePreferences(1, $data, 1);
        $this->assertSame($preference, $result);
    }

    public function test_prepare_preferences_handles_json_strings(): void
    {
        $data = [
            'content_types' => '["news", "blog"]',
            'category_preferences' => '[1, 2, 3]'
        ];

        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();

        $this->preferenceRepository->shouldReceive('updatePreferences')
            ->with(1, Mockery::on(function ($prepared) {
                return is_array($prepared['content_types'])
                    && is_array($prepared['category_preferences'])
                    && count($prepared['content_types']) === 2
                    && count($prepared['category_preferences']) === 3;
            }), 1)
            ->once()
            ->andReturn($preference);

        $result = $this->service->updatePreferences(1, $data, 1);
        $this->assertInstanceOf(MemberSubscriptionPreference::class, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->preferenceRepository = Mockery::mock(MemberSubscriptionPreferenceRepository::class);
        $this->subscriberRepository = Mockery::mock(SubscriberRepository::class);

        $this->service = new MemberSubscriptionService(
            $this->memberRepository,
            $this->preferenceRepository,
            $this->subscriberRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}