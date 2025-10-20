<?php

declare(strict_types=1);

namespace Tetthys\SqlView\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base class for read-only SQL VIEW models (Laravel, PHP 8.3+).
 *
 * Key points:
 * - Read-only: blocks any write operation (insert/update/delete/forceDelete/restore/increment/decrement/touch/push).
 * - Auto-ensure the underlying SQL VIEW exists on boot (child must implement ensureViewExists()).
 * - Helpers to inspect existence, columns, count, chunking, etc.
 *
 * Minimal child example:
 *   use App\Support\SqlView\ProductCardView;
 *
 *   final class ProductCard extends ViewModel {
 *       // Optional: if not set, we will try to use static::viewName()
 *       protected $table = 'product_card';
 *
 *       protected static function ensureViewExists(): void {
 *           ProductCardView::ensureExists();
 *       }
 *
 *       // Optional but recommended: bind the canonical view name
 *       protected static function viewName(): string {
 *           return 'product_card';
 *       }
 *   }
 */
abstract class ViewModel extends Model
{
    /** @inheritdoc */
    public $timestamps = false;

    /** @inheritdoc */
    public $incrementing = false;

    /** Allow mass-assignment for hydration (read-only model). */
    protected $guarded = [];

    /**
     * Child must ensure that the underlying SQL view exists (e.g., call YourView::ensureExists()).
     */
    abstract protected static function ensureViewExists(): void;

    /**
     * Optionally override to declare the canonical view name.
     * If not provided and $table is empty, you must set $table in the child.
     */
    protected static function viewName(): ?string
    {
        return null;
    }

    /**
     * Ensure the SQL view exists and set $table lazily when booted.
     */
    protected static function booted(): void
    {
        // Ensure the SQL VIEW exists.
        static::ensureViewExists();

        // If $table is not set but the child provided a viewName(), adopt it.
        if (empty((new static())->getTable())) {
            $name = static::viewName();
            if (is_string($name) && $name !== '') {
                // Set on a fresh instance to avoid mutating all instances unexpectedly.
                $instance = new static();
                $instance->setTable($name);
            }
        }
    }

    /* -----------------------------------------------------------------
     | Read-only enforcement: hard blocks on mutating operations
     |------------------------------------------------------------------ */

    /** Centralized guard for write operations. */
    protected function guardWriteOperation(string $op): never
    {
        throw new \LogicException(static::class . " is a read-only SQL VIEW model. Operation '{$op}' is not allowed.");
    }

    /** @inheritdoc */
    public function save(array $options = []): never
    {
        $this->guardWriteOperation('save');
    }

    /** @inheritdoc */
    public function update(array $attributes = [], array $options = []): never
    {
        $this->guardWriteOperation('update');
    }

    /** @inheritdoc */
    public function delete(): never
    {
        $this->guardWriteOperation('delete');
    }

    /** Soft delete variants (if SoftDeletes was accidentally mixed in). */
    public function forceDelete(): never
    {
        $this->guardWriteOperation('forceDelete');
    }

    public function restore(): never
    {
        $this->guardWriteOperation('restore');
    }

    /** Relation graph persistence. */
    public function push(): never
    {
        $this->guardWriteOperation('push');
    }

    /** Counters & timestamps. */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        $this->guardWriteOperation('increment');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        $this->guardWriteOperation('decrement');
    }

    public function touch(array $with = null): never
    {
        $this->guardWriteOperation('touch');
    }

    /** Low-level Eloquent hooks (defensive). */
    protected function performInsert(Builder $query): never
    {
        $this->guardWriteOperation('performInsert');
    }

    protected function performUpdate(Builder $query, array $options = []): never
    {
        $this->guardWriteOperation('performUpdate');
    }

    protected function performDeleteOnModel(): never
    {
        $this->guardWriteOperation('performDeleteOnModel');
    }

    /* -----------------------------------------------------------------
     | Query / inspection helpers (read-only friendly)
     |------------------------------------------------------------------ */

    /**
     * Check if the underlying view/table exists in the current connection schema.
     */
    public static function tableExists(): bool
    {
        $instance = new static();
        $table = $instance->getTable();

        if ($table === null || $table === '') {
            $name = static::viewName();
            if (is_string($name) && $name !== '') {
                $table = $name;
            }
        }

        return $table ? Schema::hasTable($table) : false;
    }

    /**
     * Check if the underlying object is registered as a VIEW (MySQL/MariaDB).
     * Falls back to Schema::hasTable() if information_schema is unavailable.
     */
    public static function isView(): bool
    {
        $instance = new static();
        $table = $instance->getTable() ?: static::viewName();

        if (!is_string($table) || $table === '') {
            return false;
        }

        try {
            $db = DB::getDatabaseName();
            /** @var bool $exists */
            $exists = DB::table('information_schema.VIEWS')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->exists();

            return $exists;
        } catch (\Throwable) {
            // information_schema not accessible on some platforms; fallback.
            return self::tableExists();
        }
    }

    /**
     * Return the column listing for the view (via Schema).
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        $instance = new static();
        $table = $instance->getTable() ?: static::viewName();

        return (is_string($table) && $table !== '') ? Schema::getColumnListing($table) : [];
    }

    /**
     * Count rows quickly (shortcut).
     */
    public static function rowsCount(): int
    {
        /** @var int $count */
        $count = (int) static::query()->count();
        return $count;
    }

    /**
     * Chunk over rows in a read-only manner.
     */
    public static function chunkReadOnly(int $count, Closure $callback): bool
    {
        return static::query()->chunk($count, $callback);
    }

    /**
     * Iterate each row (memory-friendly).
     */
    public static function eachReadOnly(int $count, ?Closure $callback = null): \Generator|bool
    {
        if ($callback) {
            return static::query()->each($callback, $count);
        }

        // Generator mode (no callback)
        foreach (static::query()->lazy($count) as $row) {
            yield $row;
        }
        return true;
    }

    /**
     * Convert entire view to a Collection (be careful on large views).
     */
    public static function allRows(): Collection
    {
        /** @var Collection $c */
        $c = static::query()->get();
        return $c;
    }

    /**
     * Scope: where primary key in list (works even if PK is non-incrementing).
     */
    public function scopeWhereIds(Builder $query, array $ids): Builder
    {
        $key = $this->getKeyName();
        return $query->whereIn($key, $ids);
    }

    /**
     * Prevents relations from being used to write. If someone declares a relation,
     * the relation object is still returned (for read), but save-related methods
     * will fail at the model-level guards.
     */
    protected function newRelatedInstance($class): Model|Relation
    {
        return parent::newRelatedInstance($class);
    }
}
