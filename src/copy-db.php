<?php

$host = '127.0.0.1'; // use 'db' only if running inside Docker container
$user = 'root';
$pass = 'rootsecret';

$structureDb = 'test_db';
$dataDb      = 'block_cms_backup';
$targetDb    = 'block_cms';

$migrationTable = 'migrations';

$pdo = new PDO(
    "mysql:host={$host};port=3306;charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function qi(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function tableExists(PDO $pdo, string $db, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME = :table
          AND TABLE_TYPE = 'BASE TABLE'
    ");

    $stmt->execute([
        'db' => $db,
        'table' => $table,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function getColumns(PDO $pdo, string $db, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME = :table
        ORDER BY ORDINAL_POSITION
    ");

    $stmt->execute([
        'db' => $db,
        'table' => $table,
    ]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function rowCount(PDO $pdo, string $db, string $table): int
{
    return (int) $pdo
        ->query("SELECT COUNT(*) FROM " . qi($db) . "." . qi($table))
        ->fetchColumn();
}

function copyCommonColumnData(
    PDO $pdo,
    string $sourceDb,
    string $targetDb,
    string $table,
    bool $ignoreDuplicates = true
): array {
    if (! tableExists($pdo, $sourceDb, $table)) {
        return [
            'status' => 'missing_source',
            'inserted' => 0,
            'skipped' => 0,
            'source_count' => 0,
            'columns' => 0,
        ];
    }

    $targetColumns = getColumns($pdo, $targetDb, $table);
    $sourceColumns = getColumns($pdo, $sourceDb, $table);

    $commonColumns = array_values(array_intersect($targetColumns, $sourceColumns));

    if (empty($commonColumns)) {
        return [
            'status' => 'no_matching_columns',
            'inserted' => 0,
            'skipped' => 0,
            'source_count' => 0,
            'columns' => 0,
        ];
    }

    $columnSql = implode(', ', array_map('qi', $commonColumns));

    $sourceCount = rowCount($pdo, $sourceDb, $table);
    $beforeCount = rowCount($pdo, $targetDb, $table);

    $insertMode = $ignoreDuplicates ? 'INSERT IGNORE' : 'INSERT';

    $pdo->exec("
        {$insertMode} INTO " . qi($targetDb) . "." . qi($table) . " ({$columnSql})
        SELECT {$columnSql}
        FROM " . qi($sourceDb) . "." . qi($table) . "
    ");

    $afterCount = rowCount($pdo, $targetDb, $table);

    $inserted = $afterCount - $beforeCount;
    $skipped = max(0, $sourceCount - $inserted);

    return [
        'status' => 'copied',
        'inserted' => $inserted,
        'skipped' => $skipped,
        'source_count' => $sourceCount,
        'columns' => count($commonColumns),
    ];
}

try {
    echo "Starting database rebuild...\n";
    echo "Structure source: {$structureDb}\n";
    echo "Main data source: {$dataDb}\n";
    echo "Migration data source: {$structureDb}\n";
    echo "Target: {$targetDb}\n\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $stmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ");

    $stmt->execute(['db' => $structureDb]);

    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        throw new RuntimeException("No tables found in {$structureDb}");
    }

    echo "Found " . count($tables) . " tables in {$structureDb}.\n\n";

    foreach ($tables as $table) {
        echo "Rebuilding structure: {$table}\n";

        $pdo->exec("DROP TABLE IF EXISTS " . qi($targetDb) . "." . qi($table));

        $pdo->exec(
            "CREATE TABLE " . qi($targetDb) . "." . qi($table) .
            " LIKE " . qi($structureDb) . "." . qi($table)
        );
    }

    echo "\nStructures rebuilt.\n\n";

    $totalInserted = 0;
    $totalSkipped = 0;

    foreach ($tables as $table) {
        $sourceDbForTable = $table === $migrationTable
            ? $structureDb
            : $dataDb;

        $sourceLabel = $table === $migrationTable
            ? "{$structureDb} (migration sync)"
            : $dataDb;

        echo "Copying data: {$table} from {$sourceLabel}\n";

        $result = copyCommonColumnData(
            $pdo,
            $sourceDbForTable,
            $targetDb,
            $table,
            true
        );

        if ($result['status'] === 'missing_source') {
            echo "Skipped {$table}: not found in {$sourceDbForTable}\n";
            continue;
        }

        if ($result['status'] === 'no_matching_columns') {
            echo "Skipped {$table}: no matching columns\n";
            continue;
        }

        $totalInserted += $result['inserted'];
        $totalSkipped += $result['skipped'];

        echo "Source rows: {$result['source_count']}, columns: {$result['columns']}, inserted: {$result['inserted']}";

        if ($result['skipped'] > 0) {
            echo ", skipped: {$result['skipped']}";
        }

        echo "\n";
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\nDone.\n";
    echo "Total inserted: {$totalInserted}\n";
    echo "Total skipped: {$totalSkipped}\n";
    echo "\nNext check:\n";
    echo "php artisan migrate\n";
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
        // ignore
    }

    echo "\nFAILED: " . $e->getMessage() . "\n";
    exit(1);
}