<?php

declare(strict_types=1);

namespace Tetthys\SqlView\Support;

/**
 * Framework-independent SQL utilities.
 */
final class SqlHelper
{
    /** MySQL-safe identifier quoting (backtick escaping). */
    public static function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Naive placeholder substitution: ":key" => value (string cast).
     * Override at the view level for complex templating.
     *
     * @param array<string, scalar|\Stringable> $params
     */
    public static function applyParams(string $sql, array $params): string
    {
        foreach ($params as $key => $value) {
            $sql = str_replace(':' . $key, (string) $value, $sql);
        }
        return $sql;
    }
}
