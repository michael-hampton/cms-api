<?php

namespace App\Tests\Support;

use App\Framework\Container;
use App\Framework\Events\EventDispatcher;
use PHPUnit\Framework\Assert;

class CapturingEventDispatcher extends EventDispatcher
{
    /** @var list<object> */
    public array $dispatched = [];

    public function __construct(private readonly bool $forward = true)
    {
    }

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;

        if ($this->forward) {
            parent::dispatch($event);
        }
    }

    public static function fake(bool $forward = false): self
    {
        $dispatcher = new self($forward);

        Container::getInstance()->instance(EventDispatcher::class, $dispatcher);

        return $dispatcher;
    }

    public function assertDispatched(string $eventClass, ?callable $predicate = null): object
    {
        foreach ($this->dispatched as $event) {
            if ($event instanceof $eventClass && ($predicate === null || $predicate($event))) {
                Assert::assertTrue(true);

                return $event;
            }
        }

        Assert::fail(sprintf('Expected event [%s] to be dispatched.', $eventClass));
    }

    public function assertNotDispatched(string $eventClass): void
    {
        foreach ($this->dispatched as $event) {
            Assert::assertFalse(
                $event instanceof $eventClass,
                sprintf('Expected event [%s] not to be dispatched.', $eventClass)
            );
        }

        Assert::assertTrue(true);
    }
}
