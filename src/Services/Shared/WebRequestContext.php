<?php

namespace App\Services\Shared;

class WebRequestContext implements RequestContext
{
    public function getUserId(): ?int
    {
        return auth()->id();
    }

    public function getSessionId(): string
    {
        return session_id();
    }

    public function getIpAddress(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}