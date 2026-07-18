<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\CommunicationChannelStrategy;
use App\Models\Member;
use App\Models\SubscriptionCommunication;
use App\Services\Subscriptions\Communications\CommunicationChannelResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommunicationChannelResolverTest extends TestCase
{
    private CommunicationChannelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CommunicationChannelResolver();
    }

    public function test_all_strategy_returns_configured_channels_unchanged(): void
    {
        $communication = $this->makeCommunication(['email', 'in_app'], CommunicationChannelStrategy::ALL);
        $member = $this->makeMember('member@example.com');

        $this->assertSame(['email', 'in_app'], $this->resolver->resolve($communication, $member));
    }

    public function test_fallback_strategy_picks_email_when_member_has_email(): void
    {
        $communication = $this->makeCommunication(['email', 'letter'], CommunicationChannelStrategy::EMAIL_WITH_LETTER_FALLBACK);
        $member = $this->makeMember('member@example.com');

        $this->assertSame(['email'], $this->resolver->resolve($communication, $member));
    }

    public function test_fallback_strategy_picks_letter_when_member_has_no_email(): void
    {
        $communication = $this->makeCommunication(['email', 'letter'], CommunicationChannelStrategy::EMAIL_WITH_LETTER_FALLBACK);
        $member = $this->makeMember('');

        $this->assertSame(['letter'], $this->resolver->resolve($communication, $member));
    }

    public function test_fallback_strategy_picks_letter_when_member_is_null(): void
    {
        $communication = $this->makeCommunication(['email', 'letter'], CommunicationChannelStrategy::EMAIL_WITH_LETTER_FALLBACK);

        $this->assertSame(['letter'], $this->resolver->resolve($communication, null));
    }

    private function makeCommunication(array $channels, CommunicationChannelStrategy $strategy): SubscriptionCommunication
    {
        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->channels = $channels;
        $communication->channel_strategy = $strategy;
        return $communication;
    }

    private function makeMember(?string $email): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = $email;
        return $member;
    }
}
