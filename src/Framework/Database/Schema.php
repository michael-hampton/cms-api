<?php

namespace App\Framework\Database;

// ===== Migration System =====

use App\Framework\Migration\Blueprint;

class Schema
{
    private static $database;

    public static function setDatabase(Database $database): void
    {
        self::$database = $database;
    }

    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $sql = $blueprint->toSql();

        self::$database->query($sql);
    }

    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);

        $statements = $blueprint->toAlterSql();
        foreach ($statements as $sql) {
            self::$database->query($sql);
        }
    }

    public static function drop(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        self::$database->query($sql);
    }

    public static function dropIfExists(string $table): void
    {
        self::drop($table);
    }

    public static function hasTable(string $table): bool
    {
        $sql = "SHOW TABLES LIKE :table";
        $stmt = self::$database->query($sql, ['table' => $table]);
        return $stmt->rowCount() > 0;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $sql = "SHOW COLUMNS FROM `{$table}` LIKE :column";
        $stmt = self::$database->query($sql, ['column' => $column]);
        return $stmt->rowCount() > 0;
    }
}