<?php

namespace App\Framework\FileUpload;

interface CommandExecutorInterface
{
    public function execute(string $command, &$output = null, &$returnCode = null): string|false;
    public function commandExists(string $command): bool;
}