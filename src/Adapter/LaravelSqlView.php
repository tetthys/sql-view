<?php

declare(strict_types=1);

namespace Tetthys\SqlView\Adapter;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Tetthys\SqlView\AbstractSqlView;

/**
 * Laravel adapter for SQL views (PHP 8.3+).
 * Provides additional Laravel-friendly helpers.
 */
abstract class LaravelSqlView extends AbstractSqlView
{
    /** Automatically boot DB::unprepared executor. */
    protected static function bootExecutor(): void
    {
        if (!static::$executor) {
            // Use DB::unprepared() but ignore its boolean return value
            static::setExecutor(static fn(string $sql) => DB::unprepared($sql));
        }
    }

    #[\Override]
    public static function ensureExists(): void
    {
        static::bootExecutor();
        parent::ensureExists();
    }

    #[\Override]
    public static function refresh(): void
    {
        static::bootExecutor();
        parent::refresh();
    }

    // ---------------------------------------------------------------------
    // Laravel-specific utility methods
    // ---------------------------------------------------------------------

    /** Drop this view if it exists. */
    public static function drop(): void
    {
        static::bootExecutor();
        DB::statement('DROP VIEW IF EXISTS ' . static::q(static::name()));
    }

    /** Check whether this view exists in the current database. */
    public static function exists(): bool
    {
        $dbName = DB::getDatabaseName();

        return DB::table('information_schema.views')
            ->where('table_schema', $dbName)
            ->where('table_name', static::name())
            ->exists();
    }

    /** Return a query builder for this view. */
    public static function query(): Builder
    {
        return DB::table(static::name());
    }

    /** Fetch all rows as a Collection. */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /** Dump resolved SQL content for inspection. */
    public static function dumpSql(): string
    {
        $path = base_path(static::SQL_FILE);
        if (!File::exists($path)) {
            return sprintf('-- SQL file not found: %s', $path);
        }

        $sql = File::get($path);
        foreach (static::$params as $k => $v) {
            $sql = str_replace(':' . $k, (string) $v, $sql);
        }

        return $sql;
    }

    /** Run EXPLAIN SELECT * FROM view (MySQL). */
    public static function explain(): array
    {
        $rows = DB::select('EXPLAIN SELECT * FROM ' . static::q(static::name()));
        return array_map(static fn($r) => (array) $r, $rows);
    }

    /** Return count(*) of rows in the view. */
    public static function count(): int
    {
        return (int) static::query()->count();
    }
}
