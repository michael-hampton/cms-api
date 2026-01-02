<?php

namespace App\Framework\ServiceProvider;

use App\Framework\AutoDiscovery;
use App\Framework\Support\Logger;

/**
 * Parser Service Provider - Block parsers and registry
 */
class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(\App\Parsers\BlockRegistry::class, function () {
            return $this->createBlockRegistry();
        });
    }

    private function createBlockRegistry(): \App\Parsers\BlockRegistry
    {
        $registry = new \App\Parsers\BlockRegistry();

        // Auto-discover all parser classes
        $parsers = AutoDiscovery::discoverByDir(null, 'Parsers');

        foreach ($parsers as $parserClass) {
            if (str_ends_with($parserClass, 'BlockParser') && $parserClass !== \App\Parsers\BlockRegistry::class) {
                try {
                    $parser = $this->container->resolve($parserClass);
                    $registry->register($parser);
                } catch (\Exception $e) {
                    // Log and continue - some parsers might have dependencies we can't resolve yet
                    Logger::error("Could not register parser {$parserClass}: " . $e->getMessage());
                }
            }
        }

        return $registry;
    }
}