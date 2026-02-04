<?php

namespace App\Services\Shared;

interface RequestContext
{
    public function getUserId(): ?int;

    public function getSessionId(): string;

    public function getIpAddress(): ?string;
}