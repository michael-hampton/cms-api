<?php

namespace App\Framework\Database;

use App\config\DatabaseConfig;
use App\Framework\Container;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use Exception;
use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static $instance = null;
    private $connection;
    private $config;
    private $transactionLevel = 0;
    private $queryLog = [];
    private $enableQueryLog = false;

    /**
     * Callbacks registered via beforeCommit().
     * Indexed by transaction level so nested savepoints can be unwound correctly.
     * Only fired when level drops back to 0.
     *
     * @var array<int, callable[]>
     */
    private array $beforeCommitCallbacks = [];

    /**
     * Callbacks registered via afterCommit().
     * All callbacks are deferred until the outermost transaction commits.
     * Nested savepoint releases do NOT fire them.
     *
     * @var callable[]
     */
    private array $afterCommitCallbacks = [];

    public function __construct()
    {
    }

    public static function runTransaction(callable $callback): mixed
    {
        $instance = static::getInstance();

        if (!$instance) {
            throw new \RuntimeException('Database instance not initialized');
        }

        return $instance->transaction($callback);
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initialize($config);
        }

        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Transaction lifecycle hooks
    // -------------------------------------------------------------------------

    public function initialize(array $config = []): void
    {
        $this->config = $this->normalizeConfig(
            !empty($config) ? $config : $this->getConfigFromFile()
        );

        $this->enableQueryLog = Config::get('database.log_queries', false);

        $this->connect();
    }

    private function normalizeConfig(array $config): array
    {
        if (PHP_SAPI === 'cli' && !empty($config['host_cli'])) {
            $config['host'] = $config['host_cli'];
        }
        return $config;
    }

    // -------------------------------------------------------------------------
    // Core transaction methods
    // -------------------------------------------------------------------------

    private function getConfigFromFile(): array
    {
        $databaseConfig = DatabaseConfig::getConfig();
        $defaultConnection = $databaseConfig['default'];
        return $databaseConfig['connections'][$defaultConnection];
    }

    private function connect(): void
    {
        if ($this->connection) {
            return;
        }

        $startTime = microtime(true);

        try {
            $dsn = $this->buildDsn();

            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ]);

            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

            Logger::info('Database connection established', [
                'driver' => $this->config['driver'],
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'connection_time' => $connectionTime . 'ms',
            ]);

        } catch (PDOException $e) {
            Logger::error("Database connection failed", [
                'driver' => $this->config['driver'],
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function buildDsn(): string
    {
        $driver = $this->config['driver'];

        return match ($driver) {
            'mysql' => "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}",
            'pgsql' => "pgsql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']}",
            'sqlite' => "sqlite:{$this->config['database']}",
            default => throw new Exception("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Execute a callback inside a transaction.
     * beforeCommit callbacks registered inside the closure will run just before
     * the real COMMIT.  afterCommit callbacks run after the COMMIT succeeds.
     *
     * @param callable $callback
     * @return mixed The result of the callback.
     * @throws Exception on failure (rolls back and discards pending hooks).
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollBack();
            Logger::error('Transaction failed and rolled back', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Hook runners
    // -------------------------------------------------------------------------

    public function beginTransaction(): bool
    {
        try {
            if ($this->transactionLevel === 0) {
                $result = $this->connection->beginTransaction();
                Logger::debug('Database transaction started');
            } else {
                $this->connection->exec("SAVEPOINT trans{$this->transactionLevel}");
                $result = true;
                Logger::debug('Database savepoint created', ['level' => $this->transactionLevel]);
            }

            $this->transactionLevel++;
            return $result;

        } catch (Exception $e) {
            Logger::error('Failed to begin transaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function exec(string $sql, array $params = []): int
    {
        if (empty($params)) {
            return $this->connection->exec($sql);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function prepare(string $sql): PDOStatement
    {
        try {
            return $this->connection->prepare($sql);
        } catch (Exception $e) {
            Logger::error('Failed to prepare statement', ['sql' => $sql, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    public function execute(PDOStatement $stmt, array $params = []): PDOStatement
    {
        try {
            $start = microtime(true);
            $stmt->execute($params);
            $this->logQuery($stmt->queryString, $params, round((microtime(true) - $start) * 1000, 2));
            return $stmt;
        } catch (Exception $e) {
            Logger::error('Statement execution failed', ['sql' => $stmt->queryString, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------

    private function logQuery(string $sql, array $params, float $executionTime): void
    {
        if ($this->enableQueryLog) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime,
                'timestamp' => microtime(true),
            ];
        }

        if (Config::get('app.debug', false)) {
            Logger::debug('Database query executed', [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime . 'ms',
            ]);
        }
    }

    public function commit(): bool
    {
        try {
            $this->transactionLevel--;

            if ($this->transactionLevel === 0) {
                // About to commit the outermost transaction — run beforeCommit hooks first.
                $this->runBeforeCommitCallbacks();

                $result = $this->connection->commit();
                Logger::debug('Database transaction committed');

                // Transaction is now committed — run afterCommit hooks.
                $this->runAfterCommitCallbacks();

                return $result;
            }

            if ($this->transactionLevel > 0) {
                $this->connection->exec("RELEASE SAVEPOINT trans{$this->transactionLevel}");
                Logger::debug('Database savepoint released', ['level' => $this->transactionLevel]);
                // Savepoint release does NOT fire before/afterCommit callbacks.
                // They stay queued until the outermost commit.
                return true;
            }

            throw new Exception("Cannot commit transaction - no active transaction");

        } catch (Exception $e) {
            Logger::error('Failed to commit transaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function runBeforeCommitCallbacks(): void
    {
        $callbacks = $this->beforeCommitCallbacks;
        $this->beforeCommitCallbacks = [];

        foreach ($callbacks as $callback) {
            try {
                $callback($this);
            } catch (\Throwable $e) {
                Logger::error('beforeCommit callback threw — rolling back', [
                    'error' => $e->getMessage(),
                ]);
                // Re-throw so the outer transaction() call rolls back.
                throw $e;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Query execution
    // -------------------------------------------------------------------------

    private function runAfterCommitCallbacks(): void
    {
        $callbacks = $this->afterCommitCallbacks;
        $this->afterCommitCallbacks = [];

        foreach ($callbacks as $callback) {
            try {
                $callback($this);
            } catch (\Throwable $e) {
                // afterCommit is post-commit — we cannot roll back.
                // Log and continue so remaining callbacks still run.
                Logger::error('afterCommit callback threw (transaction already committed)', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function rollBack(): bool
    {
        try {
            if ($this->transactionLevel === 0) {
                throw new Exception("Cannot rollback transaction - no active transaction");
            }

            $this->transactionLevel--;

            if ($this->transactionLevel === 0) {
                // Full rollback — discard all pending hooks.
                $this->discardPendingCallbacks();

                $result = $this->connection->rollBack();
                Logger::debug('Database transaction rolled back');
                return $result;
            }

            $this->connection->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");
            Logger::debug('Database rolled back to savepoint', ['level' => $this->transactionLevel]);
            return true;

        } catch (Exception $e) {
            Logger::error('Failed to rollback transaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function discardPendingCallbacks(): void
    {
        $discardedBefore = count($this->beforeCommitCallbacks);
        $discardedAfter = count($this->afterCommitCallbacks);

        $this->beforeCommitCallbacks = [];
        $this->afterCommitCallbacks = [];

        if ($discardedBefore > 0 || $discardedAfter > 0) {
            Logger::debug('Pending transaction callbacks discarded on rollback', [
                'before_commit' => $discardedBefore,
                'after_commit' => $discardedAfter,
            ]);
        }
    }

    public static function raw($value): RawExpression
    {
        return new RawExpression($value);
    }

    /**
     * Begin a fluent query against a database table.
     */
    public static function table(string $table): QueryBuilder
    {
        $eagerLoader = Container::getInstance()->resolve(EagerLoader::class);
        return new QueryBuilder($table, $eagerLoader, self::getInstance());
    }

    /**
     * Register a callback to run just before the outermost transaction commits.
     *
     * If called inside a nested savepoint the callback is still deferred to the
     * outermost commit, matching the afterCommit semantics.
     *
     * If no transaction is active the callback is invoked immediately.
     */
    public function beforeCommit(callable $callback): void
    {
        if ($this->transactionLevel === 0) {
            // No active transaction — run immediately.
            $callback($this);
            return;
        }

        $this->beforeCommitCallbacks[] = $callback;
    }

    /**
     * Register a callback to run after the outermost transaction successfully
     * commits.  Callbacks are deferred regardless of nesting depth — they only
     * fire when transactionLevel returns to 0 after a real COMMIT.
     *
     * If no transaction is active the callback is invoked immediately.
     */
    public function afterCommit(callable $callback): void
    {
        if ($this->transactionLevel === 0) {
            // No active transaction — run immediately.
            $callback($this);
            return;
        }

        $this->afterCommitCallbacks[] = $callback;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function fetchOne(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch($fetchMode);
        return $row !== false ? $row : null;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);

        try {
            $schemaQueries = ['SHOW TABLES', 'DESCRIBE', 'EXPLAIN', 'SHOW COLUMNS'];
            $isSchemaQuery = false;
            foreach ($schemaQueries as $keyword) {
                if (stripos($sql, $keyword) === 0) {
                    $isSchemaQuery = true;
                    break;
                }
            }

            $params = array_map(function ($value) {
                return $value instanceof \DateTimeInterface
                    ? $value->format('Y-m-d H:i:s')
                    : $value;
            }, $params);

            if ($isSchemaQuery) {
                foreach ($params as $key => $value) {
                    $escaped = str_replace("'", "''", $value);
                    $sql = str_replace(":{$key}", "'{$escaped}'", $sql);
                }
                $stmt = $this->connection->prepare($sql);
                $stmt->execute();
                return $stmt;
            }

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logQuery($sql, $params, $executionTime);

            $slowQueryThreshold = Config::get('database.slow_query_threshold', 1000);
            if ($executionTime > $slowQueryThreshold) {
                Logger::warning('Slow query detected', [
                    'sql' => $sql,
                    'params' => $params,
                    'execution_time' => $executionTime . 'ms',
                ]);
            }

            return $stmt;

        } catch (\PDOException $e) {
            Logger::error('Database query failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Query failed: " . $e->getMessage());
        } catch (\Exception $exception) {
            echo $sql;
            print_r($params);
            // echo $exception->getMessage();
            die('here');
        }
    }

    public function fetch(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll($fetchMode);
    }

    public function insert(string $table, array $data): int
    {
        try {
            $processedData = [];
            foreach ($data as $key => $value) {
                $processedData[$key] = is_bool($value) ? (int)$value : $value;
            }

            $columns = array_keys($processedData);
            $quotedColumns = array_map(fn($col) => "`{$col}`", $columns);
            $placeholders = ':' . implode(', :', $columns);
            $sql = "INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders})";

            $this->query($sql, $processedData);
            $insertId = (int)$this->connection->lastInsertId();

            Logger::debug('Record inserted', ['table' => $table, 'insert_id' => $insertId]);
            return $insertId;

        } catch (Exception $e) {
            Logger::error('Insert operation failed', ['table' => $table, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function lastInsertId(): string|false
    {
        return $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        try {
            $processedData = [];
            foreach ($data as $key => $value) {
                $processedData[$key] = is_bool($value) ? (int)$value : $value;
            }

            $setParts = array_map(fn($col) => "`{$col}` = :{$col}", array_keys($processedData));
            $whereParts = array_map(fn($col) => "`{$col}` = :where_{$col}", array_keys($where));

            $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
            $whereParams = [];
            foreach ($where as $key => $value) {
                $whereParams["where_{$key}"] = $value;
            }

            $stmt = $this->query($sql, array_merge($processedData, $whereParams));
            $affectedRows = $stmt->rowCount();

            Logger::debug('Records updated', ['table' => $table, 'affected_rows' => $affectedRows]);
            return $affectedRows;
        } catch (PDOException $PDOException) {
            die('no');
            dd($PDOException->getMessage());

        } catch (Exception $e) {
            die('hete6');
            Logger::error('Update operation failed', ['table' => $table, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete(string $table, array $where): int
    {
        try {
            $whereParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($where));
            $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);
            $stmt = $this->query($sql, $where);
            $affected = $stmt->rowCount();

            Logger::debug('Records deleted', ['table' => $table, 'affected_rows' => $affected]);
            return $affected;

        } catch (Exception $e) {
            Logger::error('Delete operation failed', ['table' => $table, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Query log
    // -------------------------------------------------------------------------

    public function find(string $table, mixed $id, string $primaryKey = 'id'): ?array
    {
        $sql = "SELECT * FROM {$table} WHERE {$primaryKey} = :id LIMIT 1";
        $stmt = $this->query($sql, ['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function cursor(string $sql, array $params = []): \Generator
    {
        $stmt = $this->query($sql, $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function enableQueryLog(): void
    {
        $this->enableQueryLog = true;
    }

    public function disableQueryLog(): void
    {
        $this->enableQueryLog = false;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    public function getConnectionInfo(): array
    {
        return [
            'driver' => $this->config['driver'],
            'host' => $this->config['host'],
            'database' => $this->config['database'],
            'transaction_level' => $this->transactionLevel,
            'query_log_enabled' => $this->enableQueryLog,
            'query_count' => count($this->queryLog),
            'pending_before_commit_callbacks' => count($this->beforeCommitCallbacks),
            'pending_after_commit_callbacks' => count($this->afterCommitCallbacks),
        ];
    }

    public function close(): void
    {
        if ($this->connection && $this->transactionLevel > 0) {
            $this->connection->rollBack();
            $this->discardPendingCallbacks();
        }
        $this->connection = null;
        $this->transactionLevel = 0;
    }

    public function __destruct()
    {
        // Intentionally left empty — close() must be called explicitly.
    }
}