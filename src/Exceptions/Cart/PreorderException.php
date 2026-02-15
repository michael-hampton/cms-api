<?php

namespace App\Exceptions\Cart;

class PreorderException extends CartException
{
    public function __construct(
        string               $message,
        public readonly ?int $productId = null,
        public readonly ?int $preorderLimit = null,
    )
    {
        parent::__construct($message);
    }
}