<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Framework\Container;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Support\Logger;
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

    public function __construct(
        private readonly Container                 $container,
        private readonly Dispatcher                $dispatcher,
        private readonly Logger                    $logger,
        private readonly JobExecutionLogRepository $logRepository,
    )
    {
        parent::__construct();
    }

    public function logs(Request $request)
    {
        $logs = $this->logRepository->paginate(
            (int)$request->get('page') ?? 0,
            (int)$request->get('per_page') ?? 20,
            $request->get('job_class') ?? '',
            $request->get('status') ?? ''
        );

        return $this->resourceResponse($logs);
    }

    /**
     * Returns all concrete, instantiable classes within the allowed namespaces,
     * each paired with a human-readable short name derived from the class basename.
     *
     * The list is sorted alphabetically by short name so the dropdown is easy to
     * scan.  Only classes that have a handle() or execute() entry point are
     * included, matching exactly what execute() would accept.
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

    // -------------------------------------------------------------------------
    // HTTP entry point
    // -------------------------------------------------------------------------

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
            $job = $this->container->resolve($jobClass);
            return $this->pushToQueue($job, $jobClass);
        }

        // Create a log entry and record the execution.
        $logId = $this->logRepository->create($jobClass, $params, 'api');
        $startedAt = hrtime(true);

        $this->logRepository->markRunning($logId);

        $outputBuffer = '';
        $job = $this->container->resolve($jobClass);

        $this->logger->info('WorkflowController: executing job', [
            'job' => $jobClass,
            'mode' => $mode,
            'params' => $params,
            'log_id' => $logId,
        ]);

        try {
            ob_start();
            $result = $this->callEntryPoint($job, $jobClass, $params);
            $outputBuffer = (string)ob_get_clean();

            $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);

            $resultArray = null;
            if (is_array($result)) {
                $resultArray = $result;
            } elseif (is_object($result) && method_exists($result, 'toLogContext')) {
                $resultArray = $result->toLogContext();
            }

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

    private function pushToQueue(object $job, string $jobClass): null
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException(
                "Class {$jobClass} does not extend " . Job::class . " and cannot be pushed to the queue."
            );
        }

        $this->dispatcher->dispatch($job);
        return null;
    }

    // -------------------------------------------------------------------------
    // Entry point resolution
    // -------------------------------------------------------------------------

    private function callEntryPoint(object $job, string $jobClass, array $params): mixed
    {
        $method = $this->resolveEntryPointMethod($job, $jobClass);
        $reflection = new \ReflectionMethod($job, $method);
        $args = $this->buildPrimitiveArgs($reflection, $params, $jobClass);

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

    private function buildPrimitiveArgs(\ReflectionMethod $method, array $params, string $jobClass): array
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

    public function runFromConsole(string $jobClass, array $params = [], string $mode = 'sync'): mixed
    {
        return $this->execute($jobClass, $params, $mode);
    }

    /**
     * Walk the filesystem roots that correspond to the allowed namespaces and
     * collect every class that is instantiable and has a handle() or execute()
     * method.
     *
     * @return array<int, array{fqn: string, name: string}>
     */
    private function discoverJobClasses(): array
    {
        // Map each allowed namespace prefix to its filesystem root.
        // Adjust the paths to match your project layout.
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

                if ($fqn === null) {
                    continue;
                }

                // Autoload the class so reflection works without manual require.
                if (!class_exists($fqn, true)) {
                    continue;
                }

                try {
                    $ref = new \ReflectionClass($fqn);
                } catch (\ReflectionException) {
                    continue;
                }

                // Skip abstract classes, interfaces, traits, and enums.
                if (!$ref->isInstantiable()) {
                    continue;
                }

                // Only include classes the controller can actually call.
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

    /**
     * Convert an absolute file path back to a fully-qualified class name.
     *
     * @param string $filePath Absolute path to the PHP file.
     * @param string $directoryRoot The filesystem root for this namespace.
     * @param string $namespaceRoot The corresponding namespace prefix (with trailing \\).
     */
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

        // Strip common suffixes so they don't pollute every label.
        $basename = preg_replace('/(Job|Command|Handler)$/', '', $className) ?? $className;

        // Split PascalCase into words.
        $label = (string)preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $basename);

        // Use the immediate parent namespace as a context hint if it is not a
        // generic segment (Jobs / Console).
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