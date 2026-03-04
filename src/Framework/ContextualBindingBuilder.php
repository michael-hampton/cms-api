<?php

namespace App\Framework;

use Closure;

class ContextualBindingBuilder
{
    private string $abstract;

    public function __construct(
        private Container $container,
        private string    $concrete,
    )
    {
    }

    public function needs(string $abstract): static
    {
        $this->abstract = $abstract;
        return $this;
    }

    public function give(Closure|string $implementation): void
    {
        $this->container->addContextualBinding(
            $this->concrete,
            $this->abstract,
            $implementation,
        );
    }
}