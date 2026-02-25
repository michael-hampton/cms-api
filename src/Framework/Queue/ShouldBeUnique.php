<?php

namespace App\Framework\Queue;

interface ShouldBeUnique
{
    public function uniqueId(): string;

    public function uniqueFor(): int; // seconds
}