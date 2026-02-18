<?php

namespace App\Imports;

class ImportOptions
{

    public function __construct(
        public int  $merchantId,
        public int  $siteId,
        public bool $updateExisting)
    {
    }
}