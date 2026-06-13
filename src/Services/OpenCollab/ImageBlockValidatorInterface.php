<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageBlockValidationResult;

interface ImageBlockValidatorInterface
{
    /**
     * Validate all image blocks in an article's block payload.
     *
     * @param  array[] $blocks     Raw block array from the article payload
     * @param  int     $siteId     The site the article belongs to
     * @param  int     $contributorId  The contributor saving/submitting
     */
    public function validateBlocks(array $blocks, int $siteId, int $contributorId): ImageBlockValidationResult;
}