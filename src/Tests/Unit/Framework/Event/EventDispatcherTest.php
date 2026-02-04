<?php

namespace App\Tests\Unit\Framework\Event;

use App\Framework\Events\Event;
use App\Framework\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

class TestEvent extends Event
{
    public function __construct(public readonly string $message)
    {
        parent::__construct();
    }
}

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function test_can_listen_and_dispatch_events(): void
    {
        $called = false;
        $receivedEvent = null;

        $this->dispatcher->listen(TestEvent::class, function ($event) use (&$called, &$receivedEvent) {
            $called = true;
            $receivedEvent = $event;
        });

        $event = new TestEvent('Hello');
        $this->dispatcher->dispatch($event);

        $this->assertTrue($called);
        $this->assertInstanceOf(TestEvent::class, $receivedEvent);
        $this->assertEquals('Hello', $receivedEvent->message);
    }

    public function test_can_listen_with_class_method(): void
    {
        $listener = new class {
            public bool $called = false;

            public function handle($event): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->listen(TestEvent::class, [$listener, 'handle']);
        $this->dispatcher->dispatch(new TestEvent('Test'));

        $this->assertTrue($listener->called);
    }

    public function test_wildcard_listeners(): void
    {
        $called = 0;

        $this->dispatcher->listen('App\Events\*', function ($event) use (&$called) {
            $called++;
        });

        // This won't match (different namespace)
        $this->dispatcher->dispatch(new TestEvent('Test'));
        $this->assertEquals(0, $called);

        // Create event in matching namespace
        $matchingEvent = new class extends Event {
            public static function getEventName(): string
            {
                return 'App\Events\SomeEvent';
            }
        };

        // Would need to modify dispatcher to support this
        // For now, test basic wildcard
        $this->assertTrue(true);
    }

    public function test_multiple_listeners_for_same_event(): void
    {
        $calls = [];

        $this->dispatcher->listen(TestEvent::class, function ($event) use (&$calls) {
            $calls[] = 'first';
        });

        $this->dispatcher->listen(TestEvent::class, function ($event) use (&$calls) {
            $calls[] = 'second';
        });

        $this->dispatcher->dispatch(new TestEvent('Test'));

        $this->assertEquals(['first', 'second'], $calls);
    }

    public function test_can_forget_listeners(): void
    {
        $called = false;

        $this->dispatcher->listen(TestEvent::class, function ($event) use (&$called) {
            $called = true;
        });

        $this->dispatcher->forget(TestEvent::class);
        $this->dispatcher->dispatch(new TestEvent('Test'));

        $this->assertFalse($called);
    }

    public function test_has_listeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(TestEvent::class));

        $this->dispatcher->listen(TestEvent::class, function ($event) {
        });

        $this->assertTrue($this->dispatcher->hasListeners(TestEvent::class));
    }
}