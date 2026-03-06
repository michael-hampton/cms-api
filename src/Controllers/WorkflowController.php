<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Framework\Container;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Support\Logger;

/**
 * Triggers any registered job or console handler on demand via HTTP or CLI.
 *
 * Intended for:
 *   - Internal tooling / admin dashboards
 *   - Manual re-runs of scheduled work without touching the queue
 *   - Console commands that need to invoke existing job logic
 *
 * Route (example):
 *   POST /internal/workflow/run
 *
 * Security: protect this route with internal/admin middleware at the router.
 * The controller enforces a namespace allowlist as a second line of defence.
 *
 * ─── Resolution contract ────────────────────────────────────────────────────
 *
 *   CONSTRUCTOR  — resolved 100% by the container.
 *                  The request payload MUST NOT supply constructor arguments.
 *                  This keeps all infrastructure deps (repos, services, etc.)
 *                  properly injected.
 *
 *   handle() / execute()
 *                — receives ONLY the primitive values from the request payload.
 *                  Typed objects (models, value objects) must be resolved by
 *                  the job itself from the primitives it receives (e.g. look up
 *                  a model by ID inside handle()).
 *
 * ─── Execution modes ────────────────────────────────────────────────────────
 *
 *   "sync"  (default) — runs handle()/execute() in the current process.
 *                        Returns whatever the method returns.
 *
 *   "queue"           — pushes the job to the queue driver and returns
 *                        immediately. Only works for Job subclasses.
 *                        Console handlers (which use execute()) cannot be queued.
 *
 * ─── Request body (JSON) ────────────────────────────────────────────────────
 *
 *   {
 *     "job":    "App\\Jobs\\Subscriptions\\GenerateIssueDeliveriesJob",
 *     "params": { "issueDeliveryId": 42 },
 *     "mode":   "sync"
 *   }
 */
class WorkflowController extends Controller
{
    /**
     * Only classes under these namespaces may be dispatched.
     */
    private const ALLOWED_NAMESPACES = [
        'App\\Jobs\\',
        'App\\Console\\',
    ];

    public function __construct(
        private readonly Container  $container,
        private readonly Dispatcher $dispatcher,
        private readonly Logger     $logger,
    )
    {
        parent::__construct();
    }

    // -------------------------------------------------------------------------
    // HTTP entry point
    // -------------------------------------------------------------------------

    public function run(Request $request): Response
    {
        $body = $this->parseBody($request);

        $jobClass = trim((string)($body['job'] ?? ''));
        $params = (array)($body['params'] ?? []);
        $mode = (string)($body['mode'] ?? 'sync');

        if ($jobClass === '') {
            return $this->jsonResponse(['error' => 'Missing required field: job'], 422);
        }

        // Guard early against bare class names — the allowlist check would catch
        // this too, but this gives a clearer actionable message to the caller.
        if (!str_contains($jobClass, '\\')) {
            return $this->jsonResponse([
                'error' => 'Invalid job class format.',
                'message' => "'{$jobClass}' looks like a bare class name. Send the fully-qualified class name, e.g. App\\Jobs\\Subscriptions\\GenerateIssueDeliveriesJob.",
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

    /**
     * Parse the request body robustly.
     *
     * Request::all() only returns parsed JSON when Content-Type: application/jsonResponse
     * is set. This method falls back to reading php://input directly so callers
     * that forget the header still work.
     */
    private function parseBody(Request $request): array
    {
        // If the framework already parsed JSON body data, use it.
        $data = $request->all();

        if (!empty($data)) {
            return $data;
        }

        // Fallback: attempt to parse raw body as JSON regardless of Content-Type.
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
    // Core
    // -------------------------------------------------------------------------

    /**
     * @throws \InvalidArgumentException on allowlist / class / signature violations
     * @throws \Throwable                on job execution failure (caller decides whether to catch)
     */
    private function execute(string $jobClass, array $params, string $mode): mixed
    {
        $this->guardAllowlist($jobClass);
        $this->guardExists($jobClass);

        // Resolve the job via the container so the constructor receives all
        // typed dependencies (services, repositories, etc.) properly.
        // The payload params are intentionally NOT passed to the constructor.
        $job = Container::getInstance()->resolve($jobClass);

        $this->logger->info('WorkflowController: executing job', [
            'job' => $jobClass,
            'mode' => $mode,
            'params' => $params,
        ]);

        if ($mode === 'queue') {
            return $this->pushToQueue($job, $jobClass);
        }

        return $this->callEntryPoint($job, $jobClass, $params);
    }

    private function guardAllowlist(string $jobClass): void
    {
        foreach (self::ALLOWED_NAMESPACES as $prefix) {
            if (str_starts_with($jobClass, $prefix)) {
                return;
            }
        }

        throw new \InvalidArgumentException(
            "Received class '{$jobClass}' is outside the allowed namespaces (" .
            implode(', ', self::ALLOWED_NAMESPACES) . "). " .
            "Ensure you are sending the fully-qualified class name with double-escaped backslashes in JSON, " .
            "e.g. \"job\": \"App\\\\Jobs\\\\Subscriptions\\\\GenerateIssueDeliveriesJob\"."
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
                "Class {$jobClass} does not extend " . Job::class . " and cannot be pushed to the queue. " .
                "Use mode=sync, or extend Job if queueing is needed."
            );
        }

        $this->dispatcher->dispatch($job);

        return null;
    }

    /**
     * Calls handle() for Job subclasses, execute() for console handlers.
     * Only primitive values from the payload are forwarded — no typed object
     * injection from HTTP params, ever.
     */
    private function callEntryPoint(object $job, string $jobClass, array $params): mixed
    {
        $method = $this->resolveEntryPointMethod($job, $jobClass);

        $reflection = new \ReflectionMethod($job, $method);
        $args = $this->buildPrimitiveArgs($reflection, $params, $jobClass);

        return $job->{$method}(...$args);
    }

    /**
     * Determine whether the class uses handle() (Job pattern) or execute()
     * (console handler pattern). Throws if neither is defined.
     */
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
    // Guards
    // -------------------------------------------------------------------------

    /**
     * Maps payload primitives to the method's parameters by name.
     *
     * Rules:
     *   - Typed, non-builtin parameters are REJECTED — jobs must resolve their
     *     own objects internally from the primitives they receive.
     *   - Named primitive found in payload → cast to declared type and use it.
     *   - Parameter has a default → use the default.
     *   - Nullable without a default → pass null.
     *   - Required primitive missing from payload → throw clearly.
     */
    private function buildPrimitiveArgs(\ReflectionMethod $method, array $params, string $jobClass): array
    {
        $args = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Typed non-builtin (e.g. IssueDelivery, Site): the job must
            // resolve these itself. We never inject objects from HTTP params.
            // Exception: nullable typed params and those with defaults are
            // satisfiable without a value — pass null or the default rather
            // than rejecting the job outright.
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // Try container resolution first — handles Laravel-style service injection.
                try {
                    $args[] = Container::getInstance()->resolve($type->getName());
                    continue;
                } catch (\Throwable) {
                    // Not resolvable from container — fall through to null/default/throw.
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
                    "is a typed object ({$type->getName()}) that could not be resolved " .
                    "from the container, has no default, and is not nullable. " .
                    "The job must accept primitives or ensure this type is container-bound."
                );
            }

            // Primitive present in payload — cast to declared type.
            if (array_key_exists($name, $params)) {
                $args[] = $this->castPrimitive($params[$name], $type);
                continue;
            }

            // Optional parameter — use its default value.
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Nullable without a default — pass null.
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new \InvalidArgumentException(
                "Required parameter '\${$name}' for {$jobClass}::{$method->getName()}() " .
                "was not found in the request payload. Pass it under params.{$name}."
            );
        }

        return $args;
    }

    /**
     * Casts a scalar payload value to the declared primitive type.
     * Untyped or mixed parameters receive the raw value unchanged.
     */
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

    /**
     * Invoke a job or console handler directly from a console command.
     *
     *   $workflow->runFromConsole(MyJob::class, ['id' => 1]);
     */
    public function runFromConsole(string $jobClass, array $params = [], string $mode = 'sync'): mixed
    {
        return $this->execute($jobClass, $params, $mode);
    }
}