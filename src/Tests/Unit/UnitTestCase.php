<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Cache\Cache;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Base for pure unit tests that must not boot the app or touch MySQL.
 *
 * Do not use MockeryPHPUnitIntegration here or on subclasses: nesting that
 * trait's assertPostConditions wrapper recurses infinitely when both parent
 * and child use it. Expectation counts are recorded below; close in tearDown.
 */
abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent accidental writes through a Database singleton left behind by
        // an earlier FunctionalTestCase in the same PHPUnit process.
        Database::resetInstance();
        Cache::flush();

        // Functional suites register real listeners on EventDispatcher. Unit
        // tests that call event() must not execute those side effects (mail,
        // relation loads, etc.) after a prior FTC class in the same process.
        Container::getInstance()->instance(EventDispatcher::class, new EventDispatcher());
    }

    protected function assertPostConditions(): void
    {
        $container = Mockery::getContainer();
        if ($container !== null) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        parent::assertPostConditions();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Database::resetInstance();
        parent::tearDown();
    }
}
