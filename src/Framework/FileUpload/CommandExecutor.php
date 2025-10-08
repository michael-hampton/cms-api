<?php

namespace App\Framework\FileUpload;

class CommandExecutor implements CommandExecutorInterface
{
    public function execute(string $command, &$output = null, &$returnCode = null): string|false
    {
        exec($command, $output, $returnCode);
        return implode("\n", $output);
    }

    public function commandExists(string $command): bool
    {
        $whereIsCommand = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $result = shell_exec("$whereIsCommand $command 2>&1");
        return !empty($result);
    }
}