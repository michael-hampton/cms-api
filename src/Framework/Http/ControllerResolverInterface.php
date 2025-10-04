<?php

namespace App\Framework\Http;

use App\Models\Page;

interface ControllerResolverInterface
{
    public function resolve(Page $page): ?string;

    public function shouldUseController(Page $page): bool;
}