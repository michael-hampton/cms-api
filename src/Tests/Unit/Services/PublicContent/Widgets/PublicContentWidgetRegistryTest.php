<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use PHPUnit\Framework\TestCase;

final class PublicContentWidgetRegistryTest extends TestCase
{
    public function test_it_registers_and_resolves_a_widget_by_key(): void
    {
        $definition = new class implements PublicContentWidgetDefinition {
            public function key(): string { return 'example'; }
            public function defaultPlacement(): WidgetPlacement
            {
                return new WidgetPlacement('example', 'after-content', 100);
            }
            public function supports(PublicContentContext $context): bool { return true; }
            public function build(PublicContentContext $context, WidgetPlacement $placement): PublicContentComponent
            {
                throw new \LogicException('Not needed by this test.');
            }
        };

        $registry = new PublicContentWidgetRegistry();
        $registry->register($definition);

        self::assertTrue($registry->has('example'));
        self::assertSame($definition, $registry->get('example'));
        self::assertSame([$definition], $registry->all());
    }
}
