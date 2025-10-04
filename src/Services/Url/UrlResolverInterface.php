<?php

namespace App\Services\Url;


interface UrlResolverInterface
{
    public function resolve(string $path): ?UrlResolutionResult;

}