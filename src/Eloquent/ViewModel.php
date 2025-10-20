<?php

declare(strict_types=1);

namespace Tetthys\SqlView\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only Eloquent base model for SQL VIEWs (PHP 8.3+).
 *
 * Key ideas:
 * - No write operations allowed.
 * - No ensureViewExists(): creation handled by LaravelSqlView.
 * - Convenient helpers for inspection, chunking, iteration, etc.
 */
abstract class ViewModel extends Model
{
    /** Read-only models do not use timestamps or auto-increment. */
    public $timestamps = false;
    public $incrementing = false;

    /** Allow mass assignment (hydration only). */
    protected $guarded = [];

    /* -----------------------------------------------------------------
     | Read-only protection
     |------------------------------------------------------------------ */
    protected function guardWrite(string $op): never
    {
        throw new \LogicException(static::class . " is a read-only SQL VIEW model. Operation '{$op}' is not allowed.");
    }

    public function save(array $options = []): never
    {
        $this->guardWrite('save');
    }
    public function update(array $attr = [], array $opt = []): never
    {
        $this->guardWrite('update');
    }
    public function delete(): never
    {
        $this->guardWrite('delete');
    }
    public function forceDelete(): never
    {
        $this->guardWrite('forceDelete');
    }
    public function restore(): never
    {
        $this->guardWrite('restore');
    }
    public function push(): never
    {
        $this->guardWrite('push');
    }
    public function increment($col, $amt = 1, array $extra = []): never
    {
        $this->guardWrite('increment');
    }
    public function decrement($col, $amt = 1, array $extra = []): never
    {
        $this->guardWrite('decrement');
    }
    public function touch($attribute = null): never
    {
        $this->guardWrite('touch');
    }

    protected function performInsert(Builder $query): never
    {
        $this->guardWrite('insert');
    }
    protected function performUpdate(Builder $query, array $opt = []): never
    {
        $this->guardWrite('update');
    }
    protected function performDeleteOnModel(): never
    {
        $this->guardWrite('delete');
    }

    /* -----------------------------------------------------------------
     | Query & inspection helpers
     |------------------------------------------------------------------ */

    /** Check if the view exists in the current schema. */
    public static function tableExists(): bool
    {
        return Schema::hasTable((new static())->getTable());
    }

    /** Check if this object is registered as a VIEW (MySQL only). */
    public static function isView(): bool
    {
        $table = (new static())->getTable();
        try {
            return DB::table('information_schema.VIEWS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->exists();
        } catch (\Throwable) {
            return self::tableExists();
        }
    }

    /** Return column names. */
    public static function columns(): array
    {
        return Schema::getColumnListing((new static())->getTable());
    }

    /** Count total rows. */
    public static function rowsCount(): int
    {
        return (int) static::query()->count();
    }

    /** Return all rows as collection. */
    public static function allRows(): Collection
    {
        return static::query()->get();
    }

    /** Chunk iteration (read-only). */
    public static function chunkReadOnly(int $count, Closure $callback): bool
    {
        return static::query()->chunk($count, $callback);
    }

    /** Lazy iterator (generator mode). */
    public static function eachReadOnly(int $count = 100): \Generator
    {
        foreach (static::query()->lazy($count) as $row) {
            yield $row;
        }
    }
}
