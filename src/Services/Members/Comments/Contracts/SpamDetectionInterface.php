<?php

namespace App\Services\Members\Comments\Contracts;

use App\DTO\Comments\CreateCommentDTO;

interface SpamDetectionInterface
{
    public function isSpam(CreateCommentDTO $dto): bool;
}