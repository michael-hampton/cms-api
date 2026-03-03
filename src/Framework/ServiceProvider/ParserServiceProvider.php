<?php

namespace App\Framework\ServiceProvider;

use App\Framework\AutoDiscovery;
use App\Framework\Support\Logger;
use App\Parsers\BlockParserInterface;
use App\Parsers\BlockRegistry;

/**
 * Parser Service Provider - Block parsers and registry
 */
class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BlockRegistry::class, function () {
            return $this->createBlockRegistry();
        });
    }

    private function createBlockRegistry(): BlockRegistry
    {
        $registry = new BlockRegistry();

        // Auto-discover all parser classes
        $parsers = AutoDiscovery::discoverByDir(null, 'Parsers');

        $baseNamespace = 'App\\Parsers\\';

        foreach ($parsers as $parserClass) {
            if (str_starts_with($parserClass, $baseNamespace) && substr_count($parserClass, '\\') !== substr_count($baseNamespace, '\\')) {
                continue;
            }

            $className = class_basename($parserClass);

            if (
                str_ends_with($className, 'BlockParser') &&
                (in_array($className, ['ZoneBlockParser'], true) ||
                    $parserClass === BlockRegistry::class)
            ) {
                continue;
            }

            if (!is_subclass_of($parserClass, BlockParserInterface::class)) {
                continue;
            }

                try {
                    $parser = $this->container->resolve($parserClass);
                    $registry->register($parser);
                } catch (\Exception $e) {
                    // Log and continue - some parsers might have dependencies we can't resolve yet
                    Logger::error("Could not register parser {$parserClass}: " . $e->getMessage());
                }

        }

        return $registry;
    }
}