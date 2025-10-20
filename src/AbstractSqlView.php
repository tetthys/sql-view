<?php

declare(strict_types=1);

namespace Tetthys\SqlView;

use Closure;
use RuntimeException;
use Tetthys\SqlView\Support\SqlHelper;

/**
 * Framework-agnostic SQL View base (PHP 8.3+).
 */
abstract class AbstractSqlView
{
    /** View name (unquoted). */
    protected const string NAME = '';

    /** Absolute or relative path to the SQL file. */
    protected const string SQL_FILE = '';

    /**
     * Optional parameter map for ":key" => value substitution.
     * @var array<string, scalar|\Stringable>
     */
    protected static array $params = [];

    /** SQL executor (e.g., PDO, DB::unprepared). */
    protected static ?Closure $executor = null;

    /** Inject or replace SQL executor. */
    public static function setExecutor(Closure $executor): void
    {
        static::$executor = $executor;
    }

    /** Return view name. */
    public static function name(): string
    {
        return static::NAME;
    }

    /** Ensure the view exists (CREATE OR REPLACE). */
    public static function ensureExists(): void
    {
        static::execute(static::sql());
    }

    /** Drop and recreate the view. */
    public static function refresh(): void
    {
        $q = SqlHelper::quote(static::name());
        static::execute("DROP VIEW IF EXISTS {$q}");
        static::execute(static::sql());
    }

    /** Load SQL text and apply params. */
    protected static function sql(): string
    {
        $path = static::SQL_FILE;

        if ($path === '' || !is_file($path)) {
            throw new RuntimeException(sprintf('SQL file not found: %s', $path));
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Failed to read SQL file: %s', $path));
        }

        return SqlHelper::applyParams($sql, static::$params);
    }

    /** Execute SQL via injected executor. */
    protected static function execute(string $sql): void
    {
        if (!static::$executor instanceof Closure) {
            throw new RuntimeException('No SQL executor set. Use setExecutor() or the Laravel adapter.');
        }

        (static::$executor)($sql);
    }
}
