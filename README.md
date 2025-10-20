# tetthys/sql-view

A **framework-agnostic SQL view base** with an optional **Laravel adapter**, written for **PHP 8.3+**.

This library lets you define SQL views as small PHP classes backed by `.sql` files,  
so you can create, refresh, drop, and inspect database views from code or migrations.  
It also provides a lightweight **Eloquent ViewModel** for read-only access to those views.

---

## 🧱 Installation

```bash
composer require tetthys/sql-view
````

If you use Laravel, the adapter and Eloquent integration will work automatically — no service provider required.

---

## ⚙️ Structure

```
sql-view/
├─ src/
│  ├─ AbstractSqlView.php       # Framework-independent core
│  ├─ Adapter/
│  │  └─ LaravelSqlView.php     # Laravel adapter (auto-wires DB::unprepared)
│  ├─ Eloquent/
│  │  └─ ViewModel.php          # Read-only Eloquent base for SQL VIEWs
│  └─ Support/
│     └─ SqlHelper.php          # Identifier quoting, param substitution
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

### ✅ 3. Read-only Eloquent ViewModel

```php
use Tetthys\SqlView\Eloquent\ViewModel;
use App\SqlViews\ProductCardView;

final class ProductCard extends ViewModel
{
    protected $table = 'product_card';
}
```

Now you can query your SQL view safely:

```php
use App\Models\ProductCard;

// Query like any Eloquent model
$rows = ProductCard::query()
    ->where('price', '>', 1000)
    ->orderByDesc('price')
    ->limit(10)
    ->get();

// Read-only protection
ProductCard::first()?->delete(); // ❌ LogicException
```

---

## 🧩 Laravel-specific helper methods

| Method           | Description                                   |
| ---------------- | --------------------------------------------- |
| `ensureExists()` | Create or replace the view                    |
| `refresh()`      | Drop and recreate the view                    |
| `drop()`         | Drop the view if it exists                    |
| `exists()`       | Check whether the view exists                 |
| `query()`        | Return a `DB::table()` builder for the view   |
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

## 🧠 ViewModel helper methods

| Method                            | Description                               |
| --------------------------------- | ----------------------------------------- |
| `rowsCount()`                     | Count total rows                          |
| `columns()`                       | Get column names                          |
| `isView()`                        | Verify that the table is a VIEW           |
| `tableExists()`                   | Check if the underlying view/table exists |
| `allRows()`                       | Fetch all rows as a `Collection`          |
| `chunkReadOnly($size, $callback)` | Iterate chunks safely                     |
| `eachReadOnly($size)`             | Lazy generator iteration                  |

---

## 🧩 Typical Laravel integration

**Migration**

```php
use Illuminate\Database\Migrations\Migration;
use App\SqlViews\ProductCardView;

return new class extends Migration {
    public function up(): void { ProductCardView::ensureExists(); }
    public function down(): void { ProductCardView::drop(); }
};
```

**Model**

```php
use App\Models\ProductCard;

ProductCard::rowsCount();      // Count rows
ProductCard::columns();        // Get column names
ProductCard::chunkReadOnly(50, fn($chunk) => ...);
```

---

## 🧠 Notes

* Placeholder replacement is simple string substitution (`:key` → value).
  For escaping or complex templating, override `sql()` in your subclass.
* Works with any SQL dialect supporting `CREATE OR REPLACE VIEW` and `DROP VIEW`.
* `ViewModel` offers a safe, fully read-only Eloquent interface.
* No service provider or configuration required.

---

## ⚖️ License

MIT © Tetthys