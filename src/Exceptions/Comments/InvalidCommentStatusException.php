<?php

namespace App\Exceptions\Comments;

class InvalidCommentStatusException extends \InvalidArgumentException
{
    public function __construct(string $status)
    {
        parent::__construct("Invalid comment status: {$status}");
    }
}