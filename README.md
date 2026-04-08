# Qubuilder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kalimulhaq/qubuilder.svg?style=flat-square)](https://packagist.org/packages/kalimulhaq/qubuilder)
[![Total Downloads](https://img.shields.io/packagist/dt/kalimulhaq/qubuilder.svg?style=flat-square)](https://packagist.org/packages/kalimulhaq/qubuilder)
![GitHub Actions](https://github.com/kalimulhaq/qubuilder/actions/workflows/main.yml/badge.svg?branch=main)

A Laravel package that converts structured JSON filter arrays into Eloquent query builder chains. Pass `select`, `filter`, `include`, `sort`, `group`, `page`, and `limit` parameters — from an HTTP request or a plain array — and get back a fully-built `Builder` instance ready to paginate or execute.

**Requires:** PHP 8.3+ · Laravel 11+

---

## Installation

```bash
composer require kalimulhaq/qubuilder
```

The service provider and facade are auto-discovered by Laravel.

### Publish Config

```bash
php artisan vendor:publish --provider="Kalimulhaq\Qubuilder\QubuilderServiceProvider" --tag="config"
```

---

## Configuration

`config/qubuilder.php`

```php
return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Parameter Names
    |--------------------------------------------------------------------------
    | Customise the URL parameter names the package reads from the request.
    | Set a key to null to use the default name shown in the comments.
    */
    'params' => [
        'select'  => null,   // default: 'select'
        'filter'  => null,   // default: 'filter'
        'include' => null,   // default: 'include'
        'sort'    => null,   // default: 'sort'
        'page'    => null,   // default: 'page'
        'limit'   => null,   // default: 'limit'
    ],

    'limit' => [
        'default' => 15,   // records per page when not specified
        'max'     => 50,   // hard cap — requests above this are clamped
    ],

];
```

---

## Quick Start

### From an HTTP Request

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

// GET /api/users?filter={"field":"status","op":"=","value":"active"}&sort={"created_at":"desc"}&limit=20
$users = Qubuilder::makeFromRequest(request(), User::class)
    ->query()
    ->paginate();
```

### From a Plain Array

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

$filters = [
    'select'  => ['id', 'name', 'email'],
    'filter'  => [
        'AND' => [
            ['field' => 'status', 'op' => '=',   'value' => 'active'],
            ['field' => 'age',    'op' => '>=',  'value' => 18],
        ],
    ],
    'include' => [
        ['name' => 'orders', 'aggregate' => 'count'],
    ],
    'sort'    => ['created_at' => 'desc'],
    'page'    => 1,
    'limit'   => 20,
];

$users = Qubuilder::make($filters, User::class)->query()->paginate();
```

### From an Existing Builder

Pass any `Builder` or `Relation` instance instead of a model class string to apply filters on top of an existing query.

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

$builder = User::where('tenant_id', $tenantId);

$users = Qubuilder::make($filters, $builder)->query()->paginate();
```

---

## API Reference

### Static Constructors

| Method | Description |
|--------|-------------|
| `Qubuilder::make($filters, $model)` | Create from array + model class or builder |
| `Qubuilder::makeFromArray(array $array, $model)` | Alias for `make()` |
| `Qubuilder::makeFromRequest(?Request $req, $model)` | Parse filters from the HTTP request |

### Instance Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `->filters(array)` | `$this` | Set the filters array |
| `->model($model)` | `$this` | Set the model or builder |
| `->query()` | `Builder` | Build and return the Eloquent query |
| `->select()` | `Select` | The resolved Select object |
| `->where()` | `Where` | The resolved Where object |
| `->include()` | `Includes` | The resolved Includes object |
| `->sort()` | `Sorts` | The resolved Sorts object |
| `->page()` | `int` | Resolved page number |
| `->limit()` | `int` | Resolved per-page limit |

---

## Filters Array Structure

```php
[
    'select'  => [],    // array of column names
    'filter'  => [],    // nested condition structure
    'include' => [],    // relationships to load
    'sort'    => [],    // ['column' => 'asc|desc']
    'group'   => [],    // array of group-by columns
    'page'    => 1,
    'limit'   => 15,
]
```

---

## Select

```php
'select' => ['id', 'name', 'email']
```

Generates `->select(['id', 'name', 'email'])`. Omitting `select` defaults to `['*']`.

---

## Filter (WHERE Conditions)

Each condition is an associative array with `field`, `op`, and optionally `value`.

### Simple Condition

Conditions must always be wrapped in an array or an `AND`/`OR` group — a bare associative condition at the top level is not valid.

```php
// Correct — flat array of conditions (all joined with AND)
'filter' => [
    ['field' => 'status', 'op' => '=', 'value' => 'active'],
]

// Also correct — explicit AND group
'filter' => [
    'AND' => [
        ['field' => 'status', 'op' => '=', 'value' => 'active'],
    ],
]
```

### AND / OR Groups

```php
'filter' => [
    'AND' => [
        ['field' => 'status', 'op' => '=', 'value' => 'active'],
        ['field' => 'age',    'op' => '>=', 'value' => 18],
    ],
]
// WHERE (status = 'active' AND age >= 18)
```

Groups can be nested to any depth:

```php
'filter' => [
    'AND' => [
        ['field' => 'status', 'op' => '=', 'value' => 'active'],
        [
            'OR' => [
                ['field' => 'role', 'op' => '=', 'value' => 'admin'],
                ['field' => 'role', 'op' => '=', 'value' => 'moderator'],
            ],
        ],
    ],
]
// WHERE (status = 'active' AND (role = 'admin' OR role = 'moderator'))
```

---

## Filter Operators

### Comparison

| `op` | SQL | Default |
|------|-----|---------|
| `=` | `= value` | Yes — used when `op` is omitted |
| `!=` | `!= value` | |
| `<>` | `<> value` | |
| `>` | `> value` | |
| `<` | `< value` | |
| `>=` | `>= value` | |
| `<=` | `<= value` | |

```php
['field' => 'score', 'op' => '>=', 'value' => 90]
```

---

### List

| `op` | SQL |
|------|-----|
| `in` | `WHERE field IN (...)` |
| `not_in` | `WHERE field NOT IN (...)` |
| `between` | `WHERE field BETWEEN a AND b` |
| `not_between` | `WHERE field NOT BETWEEN a AND b` |

```php
['field' => 'status',     'op' => 'in',      'value' => ['active', 'pending']]
['field' => 'created_at', 'op' => 'between', 'value' => ['2025-01-01', '2025-12-31']]
```

---

### Null Checks

| `op` | SQL | `value` needed |
|------|-----|---------------|
| `null` | `IS NULL` | No |
| `not_null` | `IS NOT NULL` | No |

```php
['field' => 'verified_at', 'op' => 'not_null']
```

> **Soft Deletes:** Filtering on `deleted_at` automatically applies `->withTrashed()` so soft-deleted records are included in the result set.

---

### Text Search (LIKE)

| `op` | Pattern | Matches |
|------|---------|---------|
| `_like` | `%value` | ends with |
| `like_` | `value%` | starts with |
| `_like_` | `%value%` | contains |

```php
['field' => 'name', 'op' => '_like_', 'value' => 'john']
// WHERE name LIKE '%john%'
```

---

### Date & Time

Each operator extracts the specified component and compares with `=`.

| `op` | Eloquent method | Example value |
|------|----------------|--------------|
| `date` | `whereDate` | `'2025-06-15'` |
| `year` | `whereYear` | `2025` |
| `month` | `whereMonth` | `6` |
| `day` | `whereDay` | `15` |
| `time` | `whereTime` | `'14:30:00'` |

```php
['field' => 'created_at', 'op' => 'year',  'value' => 2025]
['field' => 'published_at','op' => 'date', 'value' => '2025-06-15']
```

---

### JSON Columns

| `op` | Eloquent method |
|------|----------------|
| `json_contains` | `whereJsonContains` |
| `json_not_contains` | `whereJsonDoesntContain` |

Use `->` or dot notation to target a nested key.

```php
['field' => 'settings->notifications', 'op' => 'json_contains', 'value' => 'email']
// WHERE JSON_CONTAINS(settings->'$.notifications', '"email"')
```

---

### Column Comparison

Compare two columns using `field|<operator>` syntax.

```php
['field' => 'updated_at', 'op' => 'field|>', 'value' => 'created_at']
// WHERE updated_at > created_at
```

---

### Raw WHERE

`field` is the raw SQL expression; `value` is the bindings array.

```php
['field' => 'YEAR(created_at) = ?', 'op' => 'raw', 'value' => [2025]]
// whereRaw('YEAR(created_at) = ?', [2025])
```

---

### Relationship Existence — `has` / `doesnthave`

**With count comparison** (uses `has` / `orHas`):

```php
['field' => 'orders', 'op' => 'has|>=', 'value' => 3]
// ->has('orders', '>=', 3)
```

**With sub-filters** (uses `whereHas`):

```php
[
    'field' => 'orders',
    'op'    => 'has',
    'value' => [
        'AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']],
    ],
]
// ->whereHas('orders', fn($q) => $q->where('status', 'completed'))
```

**Does not have:**

```php
['field' => 'orders', 'op' => 'doesnthave']
// ->doesntHave('orders')

[
    'field' => 'orders',
    'op'    => 'doesnthave',
    'value' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'cancelled']]],
]
// ->whereDoesntHave('orders', fn($q) => $q->where('status', 'cancelled'))
```

---

### Multi-Column — `any` / `all` / `none`

`field` accepts an array of columns. Append the per-column operator with `|`.

| `op` | Eloquent method |
|------|----------------|
| `any\|op` | `whereAny` |
| `all\|op` | `whereAll` |
| `none\|op` | `whereNone` |

```php
[
    'field' => ['first_name', 'last_name', 'email'],
    'op'    => 'any|_like_',
    'value' => 'john',
]
// ->whereAny(['first_name','last_name','email'], 'like', '%john%')
```

---

## Sort

Key-value pairs of `column => direction`. Multiple entries are applied in order.

```php
'sort' => ['created_at' => 'desc', 'name' => 'asc']
// ORDER BY created_at DESC, name ASC
```

Direction is validated — any value other than `asc` or `desc` defaults to `asc`.

### Raw Sort Expression

Prefix the column key with `raw:` to use `orderByRaw`. The resolved direction is appended.

```php
'sort' => ["raw:FIELD(status,'active','pending','inactive')" => 'asc']
// ORDER BY FIELD(status,'active','pending','inactive') ASC
```

---

## Group By

Array of column names passed to `->groupBy()`.

```php
'group' => ['status', 'type']
// GROUP BY status, type
```

---

## Include (Eager Loading)

Each include item is an array with a required `name` key (the Eloquent relation method name) and optional sub-filter keys.

### Basic

```php
'include' => [
    ['name' => 'profile'],
    ['name' => 'roles'],
]
```

### With Sub-filters

All top-level filter keys (`select`, `filter`, `include`, `sort`, `page`, `limit`) work inside include items to scope the loaded relationship.

```php
'include' => [
    [
        'name'   => 'orders',
        'select' => ['id', 'status', 'total'],
        'filter' => [
            'AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']],
        ],
        'sort'   => ['created_at' => 'desc'],
        'limit'  => 5,
    ],
]
```

### Nested Includes

```php
'include' => [
    [
        'name'    => 'orders',
        'include' => [
            ['name' => 'items', 'select' => ['id', 'product_id', 'qty']],
        ],
    ],
]
```

### Aggregate Includes

Set `aggregate` to compute a value instead of loading records. For `avg`, `sum`, `min`, `max` you must also set `field`.

| `aggregate` | Result attribute | `field` |
|------------|-----------------|---------|
| `count` | `{relation}_count` | Not required |
| `avg` | `{relation}_avg_{field}` | Required |
| `sum` | `{relation}_sum_{field}` | Required |
| `min` | `{relation}_min_{field}` | Required |
| `max` | `{relation}_max_{field}` | Required |

```php
'include' => [
    ['name' => 'orders', 'aggregate' => 'count'],
    // $user->orders_count

    ['name' => 'orders', 'aggregate' => 'sum', 'field' => 'total'],
    // $user->orders_sum_total

    ['name' => 'reviews', 'aggregate' => 'avg', 'field' => 'rating'],
    // $user->reviews_avg_rating
]
```

Aggregate includes also accept a `filter` key to scope the aggregation:

```php
[
    'name'      => 'orders',
    'aggregate' => 'sum',
    'field'     => 'total',
    'filter'    => [
        'AND' => [['field' => 'status', 'op' => '=', 'value' => 'completed']],
    ],
]
// withSum(['orders' => fn($q) => $q->where('status','completed')], 'total')
```

### Polymorphic Relations (MorphTo)

If the relation is a `MorphTo` and the model defines a `{relation}Map()` method, the package uses `morphWith()` to apply selective sub-includes per morph type.

```php
// On your model:
public function commentable(): MorphTo
{
    return $this->morphTo();
}

private function commentableMap(): array
{
    return [
        'post'  => ['author', 'tags'],
        'video' => ['channel'],
    ];
}
```

---

## HTTP Request Integration

### Form Request Classes

The package ships two `FormRequest` base classes you can extend in your controllers.

**`GetCollectionRequest`** — validates all parameters for list endpoints:

```php
use Kalimulhaq\Qubuilder\Http\Requests\GetCollectionRequest;

class ListUsersRequest extends GetCollectionRequest {}
```

Validates: `select` (JSON), `filter` (JSON), `include` (JSON), `sort` (JSON), `page` (integer), `limit` (integer, max from config).

**`GetResourceRequest`** — extends `GetCollectionRequest`, validates only `select` and `include` (suitable for single-resource endpoints).

Both expose a `->filters()` method returning the parsed array, ready to pass directly to `Qubuilder::make()`.

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

public function index(ListUsersRequest $request)
{
    return User::query()
        ->where('tenant_id', auth()->user()->tenant_id)
        ->tap(fn ($q) => Qubuilder::make($request->filters(), $q)->query())
        ->paginate($request->filters()['limit']);
}
```

---

## Testing

```bash
composer test
```

```bash
composer test-coverage   # generates HTML coverage report in /coverage
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover a security issue please email [kalim.dir@gmail.com](mailto:kalim.dir@gmail.com) rather than using the public issue tracker.

## Credits

- [Kalim ul Haq](https://github.com/kalimulhaq)
- [Usman Ejaz](https://github.com/Usman-Ejaz)
- [Rana Usman Khan](https://github.com/RanaUsman3131)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
