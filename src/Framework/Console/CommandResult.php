<?php

declare(strict_types=1);

namespace App\Framework\Console;

/**
 * Carries the outcome of a console command or job batch execution.
 *
 * Used to unify stdout reporting and structured Logger output.
 */
final class CommandResult
{
    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var string[] */
    private array $messages = [];

    private function __construct(
        public readonly string             $commandName,
        public readonly \DateTimeImmutable $startedAt,
        public int                         $processed = 0,
        public int                         $succeeded = 0,
        public int                         $failed = 0,
        public int                         $skipped = 0,
    )
    {
    }

    public static function start(string $commandName): self
    {
        return new self($commandName, new \DateTimeImmutable());
    }

    // -------------------------------------------------------------------------
    // Mutation helpers — called during execution
    // -------------------------------------------------------------------------

    public function incrementProcessed(): void
    {
        $this->processed++;
    }

    public function incrementSucceeded(): void
    {
        $this->succeeded++;
        $this->processed++;
    }

    public function incrementFailed(string $error, array $context = []): void
    {
        $this->failed++;
        $this->processed++;
        $contextSuffix = empty($context) ? '' : ' ' . json_encode($context);
        $this->errors[] = $error . $contextSuffix;
    }

    public function incrementSkipped(string $reason = ''): void
    {
        $this->skipped++;
        $this->processed++;
        if ($reason !== '') {
            $this->warnings[] = "Skipped: {$reason}";
        }
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    public function isSuccess(): bool
    {
        return $this->failed === 0;
    }

    /**
     * Structured array suitable for Logger::info / Logger::error.
     */
    public function toLogContext(): array
    {
        return [
            'command' => $this->commandName,
            'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
            'elapsed_ms' => $this->elapsedMs(),
            'processed' => $this->processed,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    public function elapsedMs(): int
    {
        return (int)((new \DateTimeImmutable())->format('Uv') - $this->startedAt->format('Uv'));
    }

    /**
     * Human-readable summary line for stdout.
     */
    public function toSummaryLine(): string
    {
        return sprintf(
            '[%s] Done. Processed: %d | Succeeded: %d | Failed: %d | Skipped: %d (%dms)',
            $this->commandName,
            $this->processed,
            $this->succeeded,
            $this->failed,
            $this->skipped,
            $this->elapsedMs(),
        );
    }
}