# tetthys/sql-view

A **framework-agnostic SQL view base** with an optional **Laravel adapter**, written for **PHP 8.3+**.

This library lets you define SQL views as small PHP classes backed by `.sql` files,  
so you can create, refresh, drop, and inspect database views from code or migrations.

---

## 🧱 Installation

```bash
composer require tetthys/sql-view
````

If you use Laravel, the adapter will work automatically — no service provider required.

---

## ⚙️ Structure

```
sql-view/
├─ src/
│  ├─ AbstractSqlView.php      # Core (framework-independent)
│  ├─ Adapter/
│  │  └─ LaravelSqlView.php    # Laravel-specific helper layer
│  └─ Support/
│     └─ SqlHelper.php         # Utility functions (quote, param substitution)
```

---

## 🚀 Usage

### ✅ 1. Framework-independent (pure PHP)

```php
use Tetthys\SqlView\AbstractSqlView;

final class DemoView extends AbstractSqlView
{
    protected const string NAME = 'demo_view';
    protected const string SQL_FILE = __DIR__ . '/sql/demo_view.sql';
    protected static array $params = ['active' => 1];
}

// Inject any executor (e.g., PDO)
DemoView::setExecutor(fn(string $sql) => $pdo->exec($sql));

// Create or refresh the view
DemoView::ensureExists();
```

---

### ✅ 2. Laravel adapter

```php
use Tetthys\SqlView\Adapter\LaravelSqlView;

final class ProductCardView extends LaravelSqlView
{
    protected const string NAME = 'product_card';
    protected const string SQL_FILE = 'resources/sql/product_card_view.sql';
    protected static array $params = ['deletedFlag' => 0];
}

// Inside Laravel (DB::unprepared auto-wired)
ProductCardView::refresh();
```

**Example SQL file (`resources/sql/product_card_view.sql`):**

```sql
CREATE OR REPLACE VIEW `product_card` AS
SELECT id, title, price
FROM products
WHERE deleted = :deletedFlag;
```

---

## 🧩 Laravel-specific helper methods

| Method           | Description                                   |
| ---------------- | --------------------------------------------- |
| `ensureExists()` | Create or replace the view                    |
| `refresh()`      | Drop and recreate the view                    |
| `drop()`         | Drop the view if it exists                    |
| `exists()`       | Check whether the view exists                 |
| `query()`        | Get a `DB::table()` builder for the view      |
| `all()`          | Return all rows as a `Collection`             |
| `dumpSql()`      | Read SQL file with parameter substitution     |
| `explain()`      | Run `EXPLAIN SELECT * FROM view` (MySQL only) |
| `count()`        | Return `count(*)` from the view               |

**Example:**

```php
if (!ProductCardView::exists()) {
    ProductCardView::ensureExists();
}

$count = ProductCardView::count();
$rows  = ProductCardView::query()->where('price', '>', 1000)->get();

dump(ProductCardView::dumpSql());
```

---

## 🧠 Notes

* Placeholder replacement is simple string substitution (`:key` → value).
  For escaping or complex templating, override `sql()` in your subclass.
* Works with any SQL dialect that supports `CREATE OR REPLACE VIEW` / `DROP VIEW`.
* No service provider or configuration is needed — this is a lightweight utility.

---

## ⚖️ License

MIT © Tetthys