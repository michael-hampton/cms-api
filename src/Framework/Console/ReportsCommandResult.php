<?php

declare(strict_types=1);

namespace App\Framework\Console;

use App\Framework\Support\Logger;

/**
 * Shared reporting logic for console commands and jobs.
 *
 * Provides:
 *   - createResult()          → start a CommandResult
 *   - reportResult()          → write both stdout + structured log
 *   - reportFailure()         → log a single item failure with context
 *   - reportWarning()         → log a non-fatal warning
 *
 * Classes using this trait must implement stdout output via $this->info() /
 * $this->error() / $this->warn() (provided by the Command base class), or
 * define their own echo-based fallbacks.
 */
trait ReportsCommandResult
{
    /**
     * Start a new CommandResult for the given command name.
     */
    protected function createResult(string $commandName): CommandResult
    {
        return CommandResult::start($commandName);
    }

    /**
     * Write final summary to stdout AND the structured log.
     *
     * Always logs. Uses Logger::error when there are failures so the entry
     * surfaces in error alerting pipelines; otherwise uses Logger::info.
     */
    protected function reportResult(CommandResult $result): void
    {
        $summary = $result->toSummaryLine();
        $context = $result->toLogContext();

        // Stdout
        if ($result->hasFailures()) {
            $this->outputError($summary);
        } else {
            $this->outputInfo($summary);
        }

        foreach ($result->messages() as $message) {
            $this->outputInfo("  → {$message}");
        }

        foreach ($result->warnings() as $warning) {
            $this->outputWarning("  ⚠ {$warning}");
        }

        foreach ($result->errors() as $error) {
            $this->outputError("  ✘ {$error}");
        }

        // Structured log
        if ($result->hasFailures()) {
            Logger::error("Command completed with failures: {$result->commandName}", $context);
        } else {
            Logger::info("Command completed: {$result->commandName}", $context);
        }
    }

    private function outputError(string $message): void
    {
        if (method_exists($this, 'error')) {
            $this->error($message);
        } else {
            echo "[ERROR] {$message}" . PHP_EOL;
        }
    }

    private function outputInfo(string $message): void
    {
        if (method_exists($this, 'info')) {
            $this->info($message);
        } else {
            echo $message . PHP_EOL;
        }
    }

    // -------------------------------------------------------------------------
    // Output delegation — Command subclasses have $this->info() etc.
    // Plain PHP classes fall back to echo.
    // -------------------------------------------------------------------------

    private function outputWarning(string $message): void
    {
        if (method_exists($this, 'warn')) {
            $this->warn($message);
        } else {
            echo "[WARN] {$message}" . PHP_EOL;
        }
    }

    /**
     * Record a single-item failure on the result and write to stdout + log
     * immediately (useful inside loops).
     */
    protected function reportFailure(
        CommandResult $result,
        string        $message,
        array         $context = [],
        ?\Throwable   $throwable = null,
    ): void
    {
        $logContext = array_merge($context, $throwable ? ['error' => $throwable->getMessage()] : []);
        $result->incrementFailed($message, $logContext);

        $this->outputError("  ✘ {$message}");
        Logger::error("{$result->commandName}: {$message}", $logContext);
    }

    /**
     * Record a non-fatal warning and write to stdout + log immediately.
     */
    protected function reportWarning(
        CommandResult $result,
        string        $message,
        array         $context = [],
    ): void
    {
        $result->addWarning($message);
        $this->outputWarning("  ⚠ {$message}");
        Logger::warning("{$result->commandName}: {$message}", $context);
    }
}