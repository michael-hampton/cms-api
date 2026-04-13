<?php

namespace App\Tests\Unit\Framework\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\AdminNotification;
use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\Channels\AdminEmailChannel;
use App\Framework\Notifications\Channels\EmailChannel;
use App\Framework\Notifications\Channels\LogChannel;
use App\Framework\Notifications\EmailableNotification;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Notifications\NotificationInterface;
use App\Framework\Support\Logger;
use PHPUnit\Framework\TestCase;

class NotificationDispatcherTest extends TestCase
{
    // ── Dispatcher ────────────────────────────────────────────────────────────

    public function testDispatchCallsSupportingChannel(): void
    {
        $notification = $this->makePlainNotification();
        $channel = $this->mockChannel(supports: true, sends: true);

        $count = $this->makeDispatcher([$channel])->dispatch($notification);

        $this->assertSame(1, $count);
    }

    private function makePlainNotification(): NotificationInterface
    {
        return new class extends AbstractNotification {
            public function subject(): string
            {
                return 'Plain';
            }
        };
    }

    private function mockChannel(bool $supports, bool $sends): ChannelInterface
    {
        $channel = \Mockery::mock(ChannelInterface::class);
        $channel->shouldReceive('supports')->andReturn($supports);
        if ($supports) {
            $channel->shouldReceive('send')->andReturn($sends);
        }
        return $channel;
    }

    private function makeDispatcher(array $channels): NotificationDispatcher
    {
        return new NotificationDispatcher($channels, $this->makeLogger());
    }

    private function makeLogger(): Logger
    {
        $logger = \Mockery::mock(Logger::class);
        $logger->shouldIgnoreMissing();
        return $logger;
    }

    // ── EmailChannel ──────────────────────────────────────────────────────────

    public function testDispatchSkipsNonSupportingChannel(): void
    {
        $notification = $this->makePlainNotification();
        $channel = $this->mockChannel(supports: false, sends: true);

        $count = $this->makeDispatcher([$channel])->dispatch($notification);

        $this->assertSame(0, $count);
    }

    public function testDispatchContinuesAfterChannelReturnsFalse(): void
    {
        $notification = $this->makePlainNotification();
        $failing = $this->mockChannel(supports: true, sends: false);
        $succeeding = $this->mockChannel(supports: true, sends: true);

        $count = $this->makeDispatcher([$failing, $succeeding])->dispatch($notification);

        $this->assertSame(1, $count);
    }

    public function testDispatchCatchesChannelExceptions(): void
    {
        $notification = $this->makePlainNotification();

        $channel = \Mockery::mock(ChannelInterface::class);
        $channel->shouldReceive('supports')->andReturn(true);
        $channel->shouldReceive('send')->andThrow(new \RuntimeException('boom'));

        $count = $this->makeDispatcher([$channel])->dispatch($notification);

        $this->assertSame(0, $count);
    }

    public function testDispatchCountsAllSuccessfulChannels(): void
    {
        $notification = $this->makePlainNotification();
        $channels = [
            $this->mockChannel(supports: true, sends: true),
            $this->mockChannel(supports: true, sends: true),
            $this->mockChannel(supports: true, sends: false),
        ];

        $this->assertSame(2, $this->makeDispatcher($channels)->dispatch($notification));
    }

    public function testEmailChannelSupportsEmailableWithRecipient(): void
    {
        $n = $this->makeEmailableNotification('test@example.com');
        $channel = $this->makeEmailChannel();

        $this->assertTrue($channel->supports($n));
    }

    private function makeEmailableNotification(?string $email, ?Mailable $mailable = null): EmailableNotification
    {
        $m = $mailable ?? $this->createStub(Mailable::class);
        return new class($email, $m) extends AbstractNotification implements EmailableNotification {
            public function __construct(?string $email, private readonly Mailable $m)
            {
                parent::__construct(null, $email);
            }

            public function subject(): string
            {
                return 'Emailable';
            }

            public function toMailable(): Mailable
            {
                return $this->m;
            }
        };
    }

    // ── AdminEmailChannel ─────────────────────────────────────────────────────

    private function makeEmailChannel(): EmailChannel
    {
        return new EmailChannel(\Mockery::mock(MailManager::class), $this->makeLogger());
    }

    public function testEmailChannelRejectsNotificationWithoutEmail(): void
    {
        $n = $this->makeEmailableNotification(null);
        $channel = $this->makeEmailChannel();

        $this->assertFalse($channel->supports($n));
    }

    public function testEmailChannelRejectsAdminNotification(): void
    {
        $n = $this->makeAdminEmailableNotification();
        $channel = $this->makeEmailChannel();

        $this->assertFalse($channel->supports($n));
    }

    // ── LogChannel ────────────────────────────────────────────────────────────

    private function makeAdminEmailableNotification(): AdminNotification&EmailableNotification
    {
        return new class extends AbstractNotification implements EmailableNotification, AdminNotification {
            public function __construct()
            {
                parent::__construct(null, null);
            }

            public function subject(): string
            {
                return 'Admin';
            }

            public function toMailable(): Mailable
            {
                return \Mockery::mock(Mailable::class);
            }
        };
    }

    public function testEmailChannelRejectsPlainNotification(): void
    {
        $n = $this->makePlainNotification();
        $channel = $this->makeEmailChannel();

        $this->assertFalse($channel->supports($n));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function testEmailChannelCallsMailManager(): void
    {
        $mailable = $this->createStub(Mailable::class);
        $n = $this->makeEmailableNotification('buyer@example.com', $mailable);

        $pending = \Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->with($mailable)->once()->andReturn(true);

        $mailManager = \Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('to')->with('buyer@example.com')->once()->andReturn($pending);

        $channel = new EmailChannel($mailManager, $this->makeLogger());
        $result = $channel->send($n);

        $this->assertTrue($result);
    }

    public function testEmailChannelReturnsFalseAndLogsOnMailerException(): void
    {
        $mailable = $this->createStub(Mailable::class);
        $n = $this->makeEmailableNotification('buyer@example.com', $mailable);

        $mailManager = \Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('to')->andThrow(new \RuntimeException('SMTP error'));

        $logger = \Mockery::mock(Logger::class);
        $logger->shouldReceive('error')->once();

        $channel = new EmailChannel($mailManager, $logger);

        $this->assertFalse($channel->send($n));
    }

    public function testAdminEmailChannelSupportsAdminEmailableNotification(): void
    {
        $n = $this->makeAdminEmailableNotification();
        $channel = $this->makeAdminEmailChannel();

        $this->assertTrue($channel->supports($n));
    }

    private function makeAdminEmailChannel(): AdminEmailChannel
    {
        return new AdminEmailChannel(\Mockery::mock(MailManager::class), $this->makeLogger(), 'admin@example.com');
    }

    public function testAdminEmailChannelRejectsNonAdminNotification(): void
    {
        $n = $this->makeEmailableNotification('test@example.com');
        $channel = $this->makeAdminEmailChannel();

        $this->assertFalse($channel->supports($n));
    }

    public function testAdminEmailChannelDeliversToConfiguredAddress(): void
    {
        $n = $this->makeAdminEmailableNotification();
        $mailable = $this->createStub(Mailable::class);

        // Override toMailable on the anonymous class
        $n = new class($mailable) extends AbstractNotification
            implements EmailableNotification, AdminNotification {
            public function __construct(private readonly Mailable $m)
            {
                parent::__construct(null, null);
            }

            public function subject(): string
            {
                return 'Admin alert';
            }

            public function toMailable(): Mailable
            {
                return $this->m;
            }
        };

        $pending = \Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->with($n->toMailable())->once()->andReturn(true);

        $mailManager = \Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('to')->with('admin@example.com')->once()->andReturn($pending);

        $channel = new AdminEmailChannel($mailManager, $this->makeLogger(), 'admin@example.com');
        $this->assertTrue($channel->send($n));
    }

    public function testLogChannelSupportsEveryNotification(): void
    {
        $channel = new LogChannel($this->makeLogger());

        $this->assertTrue($channel->supports($this->makePlainNotification()));
        $this->assertTrue($channel->supports($this->makeEmailableNotification('x@y.com')));
        $this->assertTrue($channel->supports($this->makeAdminEmailableNotification()));
    }

    public function testLogChannelAlwaysReturnsTrue(): void
    {
        $logger = \Mockery::mock(Logger::class);
        $logger->shouldReceive('info')->once();

        $channel = new LogChannel($logger);
        $this->assertTrue($channel->send($this->makePlainNotification()));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}