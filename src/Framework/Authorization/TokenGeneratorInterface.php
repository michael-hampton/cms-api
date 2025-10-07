<?php

namespace App\Framework\Authorization;

interface TokenGeneratorInterface
{
    public function generate(): string;
}