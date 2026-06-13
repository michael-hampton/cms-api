<?php

namespace App\Exceptions\OpenCollab;

class ImageLibraryAccessDeniedException extends \RuntimeException
{
    public function __construct(string $message = 'Image library access denied.')
    {
        parent::__construct($message);
    }
}