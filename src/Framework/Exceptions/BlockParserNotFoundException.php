<?php

namespace App\Framework\Exceptions;

use Exception;

class BlockParserNotFoundException extends Exception
{
    public function __construct(string $blockType)
    {
        parent::__construct("No parser found for block type: {$blockType}");
    }
}