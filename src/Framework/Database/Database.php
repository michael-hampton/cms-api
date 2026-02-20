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

    public function __construct()
    {
    }

    public function initialize(array $config = []) {
        $this->config = $this->normalizeConfig(
            !empty($config) ? $config : $this->getConfigFromFile()
        );

        $this->enableQueryLog = Config::get('database.log_queries', false);

        $this->connect();
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initialize($config);
        }

        return self::$instance;
    }

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
                PDO::ATTR_PERSISTENT => false
            ]);


            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

            Logger::info('Database connection established', [
                'driver' => $this->config['driver'],
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'connection_time' => $connectionTime . 'ms'
            ]);

        } catch (PDOException $e) {
            Logger::error("Database connection failed", [
                'driver' => $this->config['driver'],
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'error' => $e->getMessage()
            ]);
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function buildDsn(): string
    {
        $driver = $this->config['driver'];

        switch ($driver) {
            case 'mysql':
                return "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";

            case 'pgsql':
                return "pgsql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']}";

            case 'sqlite':
                return "sqlite:{$this->config['database']}";

            default:
                throw new Exception("Unsupported database driver: {$driver}");
        }
    }

    public static function raw($value)
    {
        return new RawExpression($value);
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);

        try {
            // Detect schema queries that cannot use bound parameters
            $schemaQueries = [
                'SHOW TABLES',
                'DESCRIBE',
                'EXPLAIN',
                'SHOW COLUMNS',
            ];

            $isSchemaQuery = false;
            foreach ($schemaQueries as $keyword) {
                if (stripos($sql, $keyword) === 0) {
                    $isSchemaQuery = true;
                    break;
                }
            }

            if ($isSchemaQuery) {
                // Manually replace simple placeholders for schema queries
                foreach ($params as $key => $value) {
                    // Escape single quotes in value
                    $escaped = str_replace("'", "''", $value);
                    // Replace named placeholders (e.g., :table) with quoted value
                    $sql = str_replace(":{$key}", "'{$escaped}'", $sql);
                }

                $stmt = $this->connection->prepare($sql);
                $stmt->execute();
                return $stmt;
            }

            // Normal queries use prepared statements safely
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Log query if enabled
            $this->logQuery($sql, $params, $executionTime);

            // Log slow queries (configurable threshold)
            $slowQueryThreshold = Config::get('database.slow_query_threshold', 1000); // 1 second
            if ($executionTime > $slowQueryThreshold) {
                Logger::warning('Slow query detected', [
                    'sql' => $sql,
                    'params' => $params,
                    'execution_time' => $executionTime . 'ms'
                ]);
            }

            return $stmt;
        } catch (PDOException $e) {
            Logger::error('Database query failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(string $table, array $data): int
    {
        try {
            // Cast boolean values and handle reserved words
            $processedData = [];
            foreach ($data as $key => $value) {
                // Cast booleans to integers
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                }
                $processedData[$key] = $value;
            }

            $columns = array_keys($processedData);

            // Quote column names to handle reserved words
            $quotedColumns = array_map(function($col) {
                return "`{$col}`";
            }, $columns);

            $placeholders = ':' . implode(', :', $columns);
            $sql = "INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ") VALUES ({$placeholders})";

            $this->query($sql, $processedData);
            $insertId = (int)$this->connection->lastInsertId();

            Logger::debug('Record inserted', [
                'table' => $table,
                'insert_id' => $insertId,
                'data_keys' => array_keys($processedData)
            ]);

            return $insertId;

        } catch (Exception $e) {
            Logger::error('Insert operation failed', [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        try {
            // Process data (cast booleans)
            $processedData = [];
            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                }
                $processedData[$key] = $value;
            }

            $setParts = [];
            foreach (array_keys($processedData) as $column) {
                $setParts[] = "`{$column}` = :{$column}";
            }

            $whereParts = [];
            foreach (array_keys($where) as $column) {
                $whereParts[] = "`{$column}` = :where_{$column}";
            }

            $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);

            $whereParams = [];
            foreach ($where as $key => $value) {
                $whereParams["where_{$key}"] = $value;
            }

            $params = array_merge($processedData, $whereParams);
            $stmt = $this->query($sql, $params);
            $affectedRows = $stmt->rowCount();

            Logger::debug('Records updated', [
                'table' => $table,
                'affected_rows' => $affectedRows,
                'where_conditions' => array_keys($where)
            ]);

            return $affectedRows;

        } catch (Exception $e) {
            Logger::error('Update operation failed', [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function delete(string $table, array $where): int
    {
        try {
            $whereParts = [];
            foreach (array_keys($where) as $column) {
                $whereParts[] = "{$column} = :{$column}";
            }

            $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);
            $stmt = $this->query($sql, $where);
            $affectedRows = $stmt->rowCount();

            Logger::debug('Records deleted', [
                'table' => $table,
                'affected_rows' => $affectedRows,
                'where_conditions' => array_keys($where)
            ]);

            return $affectedRows;

        } catch (Exception $e) {
            Logger::error('Delete operation failed', [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute a non-SELECT SQL statement (INSERT, UPDATE, DELETE, etc.)
     *
     * @param string $sql    The SQL query to execute
     * @param array  $params Optional associative array of bound parameters
     *
     * @return int Number of affected rows
     */
    public function exec(string $sql, array $params = []): int
    {
        if (empty($params)) {
            // Direct exec for simple statements (faster)
            return $this->connection->exec($sql);
        }

        // Prepared statement for parameterized queries
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function find(string $table, $id, string $primaryKey = 'id'): ?array
    {
        $sql = "SELECT * FROM {$table} WHERE {$primaryKey} = :id LIMIT 1";
        $stmt = $this->query($sql, ['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function beginTransaction(): bool
    {
        try {
            if ($this->transactionLevel == 0) {
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

    public function commit(): bool
    {
        try {
            $this->transactionLevel--;

            if ($this->transactionLevel == 0) {
                $result = $this->connection->commit();
                Logger::debug('Database transaction committed');
                return $result;
            } elseif ($this->transactionLevel > 0) {
                $this->connection->exec("RELEASE SAVEPOINT trans{$this->transactionLevel}");
                Logger::debug('Database savepoint released', ['level' => $this->transactionLevel]);
                return true;
            }

            throw new Exception("Cannot commit transaction - no active transaction");

        } catch (Exception $e) {
            Logger::error('Failed to commit transaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function rollBack(): bool
    {
        try {
            if ($this->transactionLevel == 0) {
                throw new Exception("Cannot rollback transaction - no active transaction");
            }

            $this->transactionLevel--;

            if ($this->transactionLevel == 0) {
                $result = $this->connection->rollBack();
                Logger::debug('Database transaction rolled back');
                return $result;
            } else {
                $this->connection->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");
                Logger::debug('Database rolled back to savepoint', ['level' => $this->transactionLevel]);
                return true;
            }

        } catch (Exception $e) {
            Logger::error('Failed to rollback transaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function enableQueryLog(): void
    {
        $this->enableQueryLog = true;
        Logger::debug('Query logging enabled');
    }

    public function disableQueryLog(): void
    {
        $this->enableQueryLog = false;
        Logger::debug('Query logging disabled');
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
        Logger::debug('Query log flushed');
    }

    private function logQuery(string $sql, array $params, float $executionTime): void
    {
        if ($this->enableQueryLog) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime,
                'timestamp' => microtime(true)
            ];
        }

        // Always log to application log if debug level
        if (Config::get('app.debug', false)) {
            Logger::debug('Database query executed', [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime . 'ms'
            ]);
        }
    }

    public function __destruct()
    {
        //$this->close();
    }

    public function close(): void
    {
        if ($this->connection && $this->transactionLevel > 0) {
            $this->connection->rollBack();
        }
        $this->connection = null;
        $this->transactionLevel = 0;
    }

    public function getConnectionInfo(): array
    {
        return [
            'driver' => $this->config['driver'],
            'host' => $this->config['host'],
            'database' => $this->config['database'],
            'transaction_level' => $this->transactionLevel,
            'query_log_enabled' => $this->enableQueryLog,
            'query_count' => count($this->queryLog)
        ];
    }

    private function normalizeConfig(array $config): array
    {
        // If running from CLI and DB_HOST_CLI is set, override
        if (PHP_SAPI === 'cli' && !empty($config['host_cli'])) {
            $config['host'] = $config['host_cli'];
        }

        return $config;
    }

    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a callback inside a transaction.
     *
     * @param callable $callback The code to execute within the transaction.
     * @return mixed The result of the callback if successful.
     * @throws Exception If the transaction fails.
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this); // Pass the Database instance if needed
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollBack();
            Logger::error('Transaction failed and rolled back', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     * @return QueryBuilder
     */
    public static function table(string $table): QueryBuilder
    {
        $eagerLoader = Container::getInstance()->resolve(EagerLoader::class);

        return new QueryBuilder($table, $eagerLoader, self::getInstance());
    }
}