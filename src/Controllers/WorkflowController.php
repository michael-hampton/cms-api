<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Framework\Container;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\DTO\JobExecutionLog;
use App\Framework\Queue\DatabaseQueueDriver;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Queue\QueueWorker;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Jobs\JobExecutionLogRepository;
use App\Requests\WorkflowRequest;

/**
 * Triggers any registered job or console handler on demand via HTTP.
 *
 * Every execution — success or failure — is recorded in job_execution_logs
 * so the Angular dashboard can display history and per-job logs.
 *
 * Security: protect this route with internal/admin middleware at the router.
 */
class WorkflowController extends Controller
{
    private const ALLOWED_NAMESPACES = [
        'App\\Jobs\\',
        'App\\Console\\',
    ];

    /**
     * Default number of jobs processed per listen() call.
     * Override via the 'limit' query/body parameter (max: LISTEN_BATCH_MAX).
     */
    private const LISTEN_BATCH_DEFAULT = 50;
    private const LISTEN_BATCH_MAX = 500;

    public function __construct(
        private readonly Dispatcher                $dispatcher,
        private readonly Logger                    $logger,
        private readonly JobExecutionLogRepository $logRepository,
        private readonly QueueWorker $queueWorker,
        private readonly DatabaseQueueDriver $queueDriver,
    )
    {
        parent::__construct();
    }

    // -------------------------------------------------------------------------
    // HTTP endpoints
    // -------------------------------------------------------------------------

    public function logs(Request $request): Response
    {
        $logs = $this->logRepository->paginate(
            (int)($request->get('page') ?? 0),
            (int)($request->get('per_page') ?? 20),
            (string)($request->get('job_class') ?? ''),
            (string)($request->get('status') ?? ''),
        );

        return $this->resourceResponse($logs);
    }

    /**
     * Execution history for a single job class, newest first.
     *
     * Query parameters:
     *   job   (string, required) — fully-qualified class name
     *   limit (int, default 50, max 200)
     */
    public function history(Request $request): Response
    {
        $jobClass = trim((string)($request->get('job') ?? ''));
        $limit = min((int)($request->get('limit') ?? 50), 200);

        if ($jobClass === '') {
            return $this->jsonResponse(['error' => 'Missing required query parameter: job'], 422);
        }

        return $this->resourceResponse([
            'job' => $jobClass,
            'history' => $this->logRepository->findByJobClass($jobClass, $limit),
        ]);
    }

    /**
     * Execution counts, either a single filtered total or a breakdown by status.
     *
     * Query parameters (all optional):
     *   job    (string) — partial match on the fully-qualified class name
     *   status (string) — pending | running | succeeded | failed | cancelled | terminated
     *
     * Passing `status` (with or without `job`) returns a single { count } total.
     * Omitting `status` returns { counts: { pending: n, running: n, ... } },
     * optionally scoped to `job`.
     */
    public function count(Request $request): Response
    {
        $jobClass = trim((string)($request->get('job') ?? ''));
        $status = trim((string)($request->get('status') ?? ''));

        if ($status !== '') {
            return $this->resourceResponse([
                'job' => $jobClass ?: null,
                'status' => $status,
                'count' => $this->logRepository->count($jobClass, $status),
            ]);
        }

        return $this->resourceResponse([
            'job' => $jobClass ?: null,
            'counts' => $this->logRepository->countByStatus($jobClass),
        ]);
    }

    /**
     * Cancel a not-yet-started execution.
     *
     * Only PENDING logs can be cancelled. For queue-mode executions this also
     * removes the underlying row from `jobs`, provided no worker has reserved
     * it yet. If a worker has already reserved/started it, cancellation is
     * refused (409) — use terminate() once it's actually running.
     */
    public function cancel(Request $request, int $id): Response
    {
        $log = $this->logRepository->find($id);

        if ($log === null) {
            return $this->jsonResponse(['error' => "Execution log #{$id} not found."], 404);
        }

        if (!$log->isPending()) {
            return $this->jsonResponse([
                'error' => "Only pending executions can be cancelled (current status: {$log->status}).",
            ], 409);
        }

        if ($log->queueJobId !== null && !$this->queueDriver->cancelPending($log->queueJobId)) {
            return $this->jsonResponse([
                'error' => 'This job has already been picked up by a worker and can no longer be cancelled. Try terminate instead.',
            ], 409);
        }

        $this->logRepository->markCancelled($id);

        $this->logger->info('WorkflowController: execution cancelled', [
            'log_id' => $id,
            'job' => $log->jobClass,
        ]);

        return $this->resourceResponse(['status' => JobExecutionLog::STATUS_CANCELLED, 'log_id' => $id]);
    }

    /**
     * Force-stop a running execution.
     *
     * Caveat: a synchronous execution runs inline within the original HTTP
     * request that triggered it, so this endpoint has no way to interrupt
     * that PHP process from a separate request. What it does is authoritative
     * bookkeeping — it flags the log as terminated (so dashboards/automation
     * stop waiting on it) and, for queue-mode jobs, removes any not-yet-reserved
     * queue row so a worker never picks it up. A job a worker has already
     * started running to completion regardless; termination here prevents
     * retries and reports the outcome as terminated rather than succeeded/failed.
     */
    public function terminate(Request $request, int $id): Response
    {
        $log = $this->logRepository->find($id);

        if ($log === null) {
            return $this->jsonResponse(['error' => "Execution log #{$id} not found."], 404);
        }

        if (!$log->isRunning()) {
            return $this->jsonResponse([
                'error' => "Only running executions can be terminated (current status: {$log->status}).",
            ], 409);
        }

        if ($log->queueJobId !== null) {
            // Best effort: no-op if a worker already reserved the row.
            $this->queueDriver->cancelPending($log->queueJobId);
        }

        $this->logRepository->markTerminated($id);

        $this->logger->warning('WorkflowController: execution force-terminated', [
            'log_id' => $id,
            'job' => $log->jobClass,
        ]);

        return $this->resourceResponse(['status' => JobExecutionLog::STATUS_TERMINATED, 'log_id' => $id]);
    }

    /**
     * Reset a failed, cancelled, or terminated execution back to pending so
     * it can be inspected as a fresh attempt or re-triggered via run().
     * Clears the previous attempt's result, output, and error data.
     */
    public function reset(Request $request, int $id): Response
    {
        $log = $this->logRepository->find($id);

        if ($log === null) {
            return $this->jsonResponse(['error' => "Execution log #{$id} not found."], 404);
        }

        if (!$log->isResettable()) {
            return $this->jsonResponse([
                'error' => "Only failed, cancelled, or terminated executions can be reset (current status: {$log->status}).",
            ], 409);
        }

        $this->logRepository->reset($id);

        $this->logger->info('WorkflowController: execution reset to pending', [
            'log_id' => $id,
            'job' => $log->jobClass,
        ]);

        return $this->resourceResponse(['status' => JobExecutionLog::STATUS_PENDING, 'log_id' => $id]);
    }

    /**
     * Returns all concrete, instantiable classes within the allowed namespaces,
     * each paired with a human-readable short name derived from the class basename.
     *
     * @return Response  JSON: { classes: Array<{ fqn: string, name: string }> }
     */
    public function classes(): Response
    {
        try {
            $discovered = $this->discoverJobClasses();
        } catch (\Throwable $e) {
            $this->logger->error('WorkflowController: class discovery failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['error' => 'Class discovery failed.'], 500);
        }

        return $this->resourceResponse(['classes' => $discovered]);
    }

    /**
     * Process a bounded batch of queued jobs synchronously and return a summary.
     *
     * This endpoint is designed to be called by a cron job or an HTTP trigger
     * (e.g. from a scheduler service). It intentionally does NOT run a daemon
     * loop — doing so inside an HTTP request would block the worker indefinitely.
     *
     * Query / body parameters:
     *   queue  (string, default "default") — which queue to drain
     *   limit  (int, default 50, max 500)  — maximum jobs to process per call
     *
     * Response:
     *   { queue: string, processed: int, limit: int }
     */
    public function listen(Request $request): Response
    {
        $queue = (string)($request->get('queue') ?? 'default');
        $limit = min(
            (int)($request->get('limit') ?? self::LISTEN_BATCH_DEFAULT),
            self::LISTEN_BATCH_MAX,
        );

        $this->logger->info('WorkflowController: running queue batch', [
            'queue' => $queue,
            'limit' => $limit,
        ]);

        $processed = $this->queueWorker->runBatch($queue, $limit);

        return $this->resourceResponse([
            'queue' => $queue,
            'processed' => $processed,
            'limit' => $limit,
        ]);
    }

    /**
     * Execute or dispatch a job class by fully-qualified name.
     *
     * Body parameters:
     *   job    (string, required) — fully-qualified class name
     *   params (object, optional) — constructor / execute() arguments
     *   mode   (string, default "sync") — "sync" | "queue"
     */
    public function run(WorkflowRequest $request): Response
    {
        $body = $this->parseBody($request);
        $jobClass = trim((string)($body['job'] ?? ''));
        $params = (array)($body['params'] ?? []);
        $mode = (string)($body['mode'] ?? 'sync');

        if ($jobClass === '') {
            return $this->jsonResponse(['error' => 'Missing required field: job'], 422);
        }

        if (!str_contains($jobClass, '\\')) {
            return $this->jsonResponse([
                'error' => 'Invalid job class format.',
                'message' => "'{$jobClass}' looks like a bare class name. Send the fully-qualified class name.",
            ], 422);
        }

        try {
            $result = $this->execute($jobClass, $params, $mode);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            $this->logger->error('WorkflowController: job execution failed', [
                'job' => $jobClass,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse([
                'error' => 'Job execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        return $this->jsonResponse([
            'status' => 'ok',
            'job' => $jobClass,
            'mode' => $mode,
            'result' => $result,
        ]);
    }

    // -------------------------------------------------------------------------
    // Console entry point
    // -------------------------------------------------------------------------

    public function runFromConsole(string $jobClass, array $params = [], string $mode = 'sync'): mixed
    {
        return $this->execute($jobClass, $params, $mode);
    }

    // -------------------------------------------------------------------------
    // Core execution (with logging)
    // -------------------------------------------------------------------------

    /**
     * @throws \InvalidArgumentException on allowlist / class / signature violations
     * @throws \Throwable                on job execution failure
     */
    private function execute(string $jobClass, array $params, string $mode): mixed
    {
        $this->guardAllowlist($jobClass);
        $this->guardExists($jobClass);

        if ($mode === 'queue') {
            $job = $this->instantiate($jobClass, $params, hydrateServices: false);
            return $this->pushToQueue($job, $jobClass, $params);
        }

        $logId = $this->logRepository->create($jobClass, $params, 'api');
        $startedAt = hrtime(true);

        $this->logRepository->markRunning($logId);

        $this->logger->info('WorkflowController: executing job', [
            'job' => $jobClass,
            'mode' => $mode,
            'params' => $params,
            'log_id' => $logId,
        ]);

        try {
            ob_start();

            $job = $this->instantiate($jobClass, $params, hydrateServices: true);
            $result = $this->callEntryPoint($job, $jobClass, $params);

            $outputBuffer = (string)ob_get_clean();
            $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);

            $resultArray = match (true) {
                is_array($result) => $result,
                is_object($result) && method_exists($result, 'toLogContext') => $result->toLogContext(),
                default => null,
            };

            $this->logRepository->markSucceeded($logId, $resultArray, $outputBuffer, $durationMs);

            return $result;

        } catch (\Throwable $e) {
            $outputBuffer = (string)ob_get_clean();
            $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);

            $this->logRepository->markFailed(
                id: $logId,
                errorMessage: $e->getMessage(),
                errorTrace: $e->getTraceAsString(),
                output: $outputBuffer,
                durationMs: $durationMs,
            );

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    private function guardAllowlist(string $jobClass): void
    {
        foreach (self::ALLOWED_NAMESPACES as $prefix) {
            if (str_starts_with($jobClass, $prefix)) {
                return;
            }
        }

        throw new \InvalidArgumentException(
            "Received class '{$jobClass}' is outside the allowed namespaces (" .
            implode(', ', self::ALLOWED_NAMESPACES) . ")."
        );
    }

    private function guardExists(string $jobClass): void
    {
        if (!class_exists($jobClass)) {
            throw new \InvalidArgumentException("Class '{$jobClass}' does not exist.");
        }
    }

    /**
     * @return array{log_id: int, queue_job_id: ?int}
     */
    private function pushToQueue(object $job, string $jobClass, array $params): array
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException(
                "Class {$jobClass} does not extend " . Job::class . " and cannot be pushed to the queue."
            );
        }

        // Queue-mode executions get a log row too (status PENDING) so
        // history/count/cancel/terminate have something to operate on —
        // previously only sync-mode executions were tracked at all.
        $logId = $this->logRepository->create($jobClass, $params, 'api');
        $queueJobId = $this->dispatcher->push($job);
        $this->logRepository->attachQueueJobId($logId, $queueJobId);

        return ['log_id' => $logId, 'queue_job_id' => $queueJobId];
    }

    // -------------------------------------------------------------------------
    // Entry point resolution
    // -------------------------------------------------------------------------

    /**
     * Call the appropriate entry point on the instantiated class.
     *
     * Jobs (BaseJob subclasses): constructor already captured all payload params;
     * handle() takes no arguments. Calling handle() with reflected params would
     * be wrong and would break if handle() happens to declare a parameter whose
     * name collides with a payload key.
     *
     * Console commands / handlers: execute() may declare parameters that map
     * directly from $params, so we resolve those via reflection.
     */
    private function callEntryPoint(object $job, string $jobClass, array $params): mixed
    {
        $method = $this->resolveEntryPointMethod($job, $jobClass);

        if ($method === 'handle') {
            // Jobs receive their data via constructor. handle() never takes params.
            return $job->handle();
        }

        // Console commands / arbitrary handlers — resolve execute() args from payload.
        $reflection = new \ReflectionMethod($job, $method);
        $args = $this->buildArgsFromParams($reflection, $params, $jobClass);

        return $job->{$method}(...$args);
    }

    private function resolveEntryPointMethod(object $job, string $jobClass): string
    {
        if (method_exists($job, 'handle')) {
            return 'handle';
        }

        if (method_exists($job, 'execute')) {
            return 'execute';
        }

        throw new \InvalidArgumentException(
            "Class {$jobClass} has neither a handle() nor an execute() method."
        );
    }

    // -------------------------------------------------------------------------
    // Instantiation
    // -------------------------------------------------------------------------

    /**
     * Build a job or command instance from the incoming payload.
     *
     * For BaseJob subclasses:
     *   - Constructor params are split into primitives (from $params) and
     *     service dependencies (from the container).
     *   - Primitives are passed via Job::for(...$args).
     *   - When $hydrateServices is true, __wakeup() is called to inject
     *     services exactly as the queue worker would after deserialisation.
     *   - When $hydrateServices is false (queue mode) services are left unset;
     *     the worker will inject them after the job is deserialised.
     *
     * For other classes: resolved directly from the container.
     */
    private function instantiate(string $jobClass, array $params, bool $hydrateServices): object
    {
        if (!is_subclass_of($jobClass, BaseJob::class)) {
            return Container::getInstance()->resolve($jobClass);
        }

        $constructor = (new \ReflectionClass($jobClass))->getConstructor();

        if ($constructor === null) {
            $job = $jobClass::for();
        } else {
            $args = $this->buildConstructorArgs($constructor, $params, $jobClass);
            $job = $jobClass::for(...$args);
        }

        if ($hydrateServices) {
            $job->__wakeup();
        }

        return $job;
    }

    /**
     * Resolve constructor parameters.
     *
     * Primitive / built-in params are pulled from $params by name.
     * Class / interface params are resolved from the container.
     * ReadOnly service properties are skipped — __wakeup() handles those.
     */
    private function buildConstructorArgs(\ReflectionMethod $constructor, array $params, string $jobClass): array
    {
        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Non-built-in type → service dependency; skip here, __wakeup() injects it.
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $args[] = null;
                }
                // readonly service props are not passed to ::for() at all;
                // ::for() mirrors __construct so missing args will be caught below
                // only if the param is required and non-nullable.
                continue;
            }

            if (array_key_exists($name, $params)) {
                $args[] = $this->castPrimitive($params[$name], $type);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new \InvalidArgumentException(
                "Required parameter '\${$name}' for {$jobClass}::__construct() " .
                "was not found in the request payload."
            );
        }

        return $args;
    }

    /**
     * Resolve execute() / handle() parameters for non-job handlers.
     * Service types are resolved from the container; primitives come from $params.
     */
    private function buildArgsFromParams(\ReflectionMethod $method, array $params, string $jobClass): array
    {
        $args = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $args[] = Container::getInstance()->resolve($type->getName());
                    continue;
                } catch (\Throwable) {
                }

                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }

                if ($param->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                throw new \InvalidArgumentException(
                    "Parameter '\${$name}' on {$jobClass}::{$method->getName()}() " .
                    "is a typed object that could not be resolved from the container."
                );
            }

            if (array_key_exists($name, $params)) {
                $args[] = $this->castPrimitive($params[$name], $type);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new \InvalidArgumentException(
                "Required parameter '\${$name}' for {$jobClass}::{$method->getName()}() " .
                "was not found in the request payload."
            );
        }

        return $args;
    }

    private function castPrimitive(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string)$value,
            default => $value,
        };
    }

    // -------------------------------------------------------------------------
    // Request parsing
    // -------------------------------------------------------------------------

    private function parseBody(Request $request): array
    {
        $data = $request->all();

        if (!empty($data)) {
            return $data;
        }

        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    // -------------------------------------------------------------------------
    // Class discovery
    // -------------------------------------------------------------------------

    /**
     * Walk the filesystem roots that correspond to the allowed namespaces and
     * collect every class that is instantiable and has a handle() or execute()
     * method.
     *
     * @return array<int, array{fqn: string, name: string}>
     */
    private function discoverJobClasses(): array
    {
        $namespacePaths = [
            'App\\Jobs\\' => __DIR__ . '/../Jobs',
            'App\\Console\\' => __DIR__ . '/../Console',
        ];

        $results = [];

        foreach ($namespacePaths as $namespacePrefix => $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fqn = $this->fileToFqn($file->getRealPath(), $directory, $namespacePrefix);

                if ($fqn === null || !class_exists($fqn, true)) {
                    continue;
                }

                try {
                    $ref = new \ReflectionClass($fqn);
                } catch (\ReflectionException) {
                    continue;
                }

                if (!$ref->isInstantiable()) {
                    continue;
                }

                if (!$ref->hasMethod('handle') && !$ref->hasMethod('execute')) {
                    continue;
                }

                $results[] = [
                    'fqn' => $fqn,
                    'name' => $this->buildHumanName($fqn),
                ];
            }
        }

        usort($results, static fn(array $a, array $b) => strcmp($a['name'], $b['name']));

        return $results;
    }

    private function fileToFqn(string $filePath, string $directoryRoot, string $namespaceRoot): ?string
    {
        $filePath = realpath($filePath);
        $directoryRoot = realpath($directoryRoot);

        if (!$filePath || !$directoryRoot) {
            return null;
        }

        if (!str_starts_with($filePath, $directoryRoot)) {
            return null;
        }

        $relative = substr($filePath, strlen($directoryRoot) + 1);

        if (!str_ends_with($relative, '.php')) {
            return null;
        }

        $relative = substr($relative, 0, -4);
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return rtrim($namespaceRoot, '\\') . '\\' . $relative;
    }

    /**
     * Turn a fully-qualified class name into a readable label.
     *
     * App\Jobs\Subscriptions\GenerateIssueDeliveriesJob
     *   → "Generate Issue Deliveries (Subscriptions)"
     */
    private function buildHumanName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        $className = array_pop($parts);

        $basename = preg_replace('/(Job|Command|Handler)$/', '', $className) ?? $className;
        $label = (string)preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $basename);

        $skip = ['Jobs', 'Console', 'Commands', 'Handlers', 'App'];
        $context = null;

        foreach (array_reverse($parts) as $part) {
            if (!in_array($part, $skip, true)) {
                $context = $part;
                break;
            }
        }

        return $context ? trim($label) . " ({$context})" : trim($label);
    }
}