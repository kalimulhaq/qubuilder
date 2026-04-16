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

> The `group` parameter name is fixed (`'group'`) and cannot be customised via the `params` config.

> **Limit clamping:** Any `limit` value above `max` is silently clamped to `max`. Values of `0` or below are also clamped to `max` (not to `1` or the default), so sending `limit=0` returns `max` records.

---

## Quick Start

### From a Plain Array

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

$filters = [
    'select'  => ['id', 'name', 'email'],
    'filter'  => [
        'AND' => [
            ['field' => 'status', 'op' => '=',  'value' => 'active'],
            ['field' => 'age',    'op' => '>=', 'value' => 18],
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

### From an HTTP Request

Both GET (query string) and POST (request body) are supported — the package reads from `$request->input()`, which transparently handles both methods.

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

// GET  /api/users?filter={"AND":[{"field":"status","op":"=","value":"active"}]}&sort={"created_at":"desc"}&limit=20
// POST /api/users  { "filter": {"AND":[{"field":"status","op":"=","value":"active"}]}, "sort": {"created_at":"desc"}, "limit": 20 }

$users = Qubuilder::makeFromRequest(request(), User::class)
    ->query()
    ->paginate();
```

You can also extend the built-in `GetCollectionRequest` to get automatic validation:

```php
use Kalimulhaq\Qubuilder\Http\Requests\GetCollectionRequest;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

class ListUsersRequest extends GetCollectionRequest {}

public function index(ListUsersRequest $request)
{
    return Qubuilder::make($request->filters(), User::class)
        ->query()
        ->paginate();
}
```

---

## Detailed Example

A single filters payload that exercises every available option — shown as both a PHP array and its JSON equivalent for use in HTTP requests.

### PHP Array

```php
$filters = [

    // ── Columns ──────────────────────────────────────────────────────────────
    'select' => ['id', 'name', 'email', 'status', 'created_at'],

    // ── WHERE Conditions ─────────────────────────────────────────────────────
    'filter' => [
        'AND' => [

            // Comparison operators: =  !=  <>  >  <  >=  <=
            ['field' => 'status',      'op' => '=',   'value' => 'active'],
            ['field' => 'age',         'op' => '>=',  'value' => 18],
            ['field' => 'score',       'op' => '!=',  'value' => 0],

            // List operators
            ['field' => 'role',        'op' => 'in',          'value' => ['admin', 'editor']],
            ['field' => 'type',        'op' => 'not_in',      'value' => ['guest', 'banned']],
            ['field' => 'created_at',  'op' => 'between',     'value' => ['2024-01-01', '2024-12-31']],
            ['field' => 'score',       'op' => 'not_between', 'value' => [0, 10]],

            // Null checks (no value needed)
            ['field' => 'verified_at', 'op' => 'not_null'],
            ['field' => 'deleted_at',  'op' => 'null'],   // auto-applies ->withTrashed()

            // LIKE / text search
            ['field' => 'name',        'op' => '_like_', 'value' => 'john'],   // LIKE '%john%'
            ['field' => 'email',       'op' => 'like_',  'value' => 'admin'],  // LIKE 'admin%'
            ['field' => 'bio',         'op' => '_like',  'value' => '.com'],   // LIKE '%.com'

            // Date & time operators
            ['field' => 'created_at',   'op' => 'date',  'value' => '2024-06-15'],
            ['field' => 'created_at',   'op' => 'year',  'value' => 2024],
            ['field' => 'created_at',   'op' => 'month', 'value' => 6],
            ['field' => 'created_at',   'op' => 'day',   'value' => 15],
            ['field' => 'published_at', 'op' => 'time',  'value' => '09:00:00'],

            // JSON column
            ['field' => 'settings->notifications', 'op' => 'json_contains',     'value' => 'email'],
            ['field' => 'settings->flags',         'op' => 'json_not_contains', 'value' => 'beta'],

            // Column-to-column comparison  (field|<operator>)
            ['field' => 'updated_at', 'op' => 'field|>', 'value' => 'created_at'],

            // Raw WHERE expression
            ['field' => 'YEAR(created_at) = ?', 'op' => 'raw', 'value' => [2024]],

            // Relationship existence
            ['field' => 'orders', 'op' => 'has|>=', 'value' => 3],             // has('orders', '>=', 3)
            [
                'field' => 'orders',
                'op'    => 'has',
                'value' => [
                    'AND' => [
                        ['field' => 'status', 'op' => '=', 'value' => 'completed'],
                    ],
                ],
            ],
            ['field' => 'invoices', 'op' => 'doesnthave'],                       // doesntHave('invoices')
            [
                'field' => 'invoices',
                'op'    => 'doesnthave',
                'value' => [
                    'AND' => [
                        ['field' => 'status', 'op' => '=', 'value' => 'cancelled'],
                    ],
                ],
            ],

            // Multi-column operators
            [
                'field' => ['first_name', 'last_name', 'email'],
                'op'    => 'any|_like_',
                'value' => 'john',
            ],  // whereAny([...], 'like', '%john%')

            // Nested OR group inside the top-level AND
            [
                'OR' => [
                    ['field' => 'country', 'op' => '=', 'value' => 'US'],
                    ['field' => 'country', 'op' => '=', 'value' => 'CA'],
                ],
            ],
        ],
    ],

    // ── Eager Loading ─────────────────────────────────────────────────────────
    'include' => [

        // Basic relation
        ['name' => 'profile'],

        // Relation with sub-filters, sort, and limit
        [
            'name'   => 'orders',
            'select' => ['id', 'status', 'total'],
            'filter' => [
                'AND' => [
                    ['field' => 'status', 'op' => '=', 'value' => 'completed'],
                ],
            ],
            'sort'   => ['created_at' => 'desc'],
            'limit'  => 5,
        ],

        // Nested include
        [
            'name'    => 'orders',
            'include' => [
                ['name' => 'items', 'select' => ['id', 'product_id', 'qty']],
            ],
        ],

        // Aggregate: count
        ['name' => 'orders', 'aggregate' => 'count'],

        // Aggregate: sum with filter scope
        [
            'name'      => 'orders',
            'aggregate' => 'sum',
            'field'     => 'total',
            'filter'    => [
                'AND' => [
                    ['field' => 'status', 'op' => '=', 'value' => 'completed'],
                ],
            ],
        ],

        // Aggregate: avg
        ['name' => 'reviews', 'aggregate' => 'avg', 'field' => 'rating'],

        // Aggregate: min / max
        ['name' => 'orders', 'aggregate' => 'min', 'field' => 'total'],
        ['name' => 'orders', 'aggregate' => 'max', 'field' => 'total'],
    ],

    // ── Sorting ───────────────────────────────────────────────────────────────
    'sort' => [
        'created_at' => 'desc',
        'name'       => 'asc',
        "raw:FIELD(status,'active','pending','inactive')" => 'asc',  // orderByRaw
    ],

    // ── Group By ──────────────────────────────────────────────────────────────
    'group' => ['status', 'role'],

    // ── Pagination ────────────────────────────────────────────────────────────
    'page'  => 1,
    'limit' => 20,
];

$users = Qubuilder::make($filters, User::class)->query()->paginate();
```

### JSON (for HTTP requests — GET or POST)

The same payload encoded as JSON query-string parameters (GET) or request body (POST):

```json
{
    "select": ["id", "name", "email", "status", "created_at"],

    "filter": {
        "AND": [
            { "field": "status",      "op": "=",   "value": "active" },
            { "field": "age",         "op": ">=",  "value": 18 },
            { "field": "score",       "op": "!=",  "value": 0 },

            { "field": "role",        "op": "in",          "value": ["admin", "editor"] },
            { "field": "type",        "op": "not_in",      "value": ["guest", "banned"] },
            { "field": "created_at",  "op": "between",     "value": ["2024-01-01", "2024-12-31"] },
            { "field": "score",       "op": "not_between", "value": [0, 10] },

            { "field": "verified_at", "op": "not_null" },
            { "field": "deleted_at",  "op": "null" },

            { "field": "name",  "op": "_like_", "value": "john" },
            { "field": "email", "op": "like_",  "value": "admin" },
            { "field": "bio",   "op": "_like",  "value": ".com" },

            { "field": "created_at",   "op": "date",  "value": "2024-06-15" },
            { "field": "created_at",   "op": "year",  "value": 2024 },
            { "field": "created_at",   "op": "month", "value": 6 },
            { "field": "created_at",   "op": "day",   "value": 15 },
            { "field": "published_at", "op": "time",  "value": "09:00:00" },

            { "field": "settings->notifications", "op": "json_contains",     "value": "email" },
            { "field": "settings->flags",         "op": "json_not_contains", "value": "beta" },

            { "field": "updated_at", "op": "field|>", "value": "created_at" },

            { "field": "YEAR(created_at) = ?", "op": "raw", "value": [2024] },

            { "field": "orders", "op": "has|>=", "value": 3 },
            {
                "field": "orders",
                "op": "has",
                "value": {
                    "AND": [{ "field": "status", "op": "=", "value": "completed" }]
                }
            },
            { "field": "invoices", "op": "doesnthave" },
            {
                "field": "invoices",
                "op": "doesnthave",
                "value": {
                    "AND": [{ "field": "status", "op": "=", "value": "cancelled" }]
                }
            },

            { "field": ["first_name", "last_name", "email"], "op": "any|_like_", "value": "john" },

            {
                "OR": [
                    { "field": "country", "op": "=", "value": "US" },
                    { "field": "country", "op": "=", "value": "CA" }
                ]
            }
        ]
    },

    "include": [
        { "name": "profile" },
        {
            "name": "orders",
            "select": ["id", "status", "total"],
            "filter": {
                "AND": [{ "field": "status", "op": "=", "value": "completed" }]
            },
            "sort": { "created_at": "desc" },
            "limit": 5
        },
        {
            "name": "orders",
            "include": [
                { "name": "items", "select": ["id", "product_id", "qty"] }
            ]
        },
        { "name": "orders",  "aggregate": "count" },
        {
            "name": "orders",
            "aggregate": "sum",
            "field": "total",
            "filter": {
                "AND": [{ "field": "status", "op": "=", "value": "completed" }]
            }
        },
        { "name": "reviews", "aggregate": "avg", "field": "rating" },
        { "name": "orders",  "aggregate": "min", "field": "total" },
        { "name": "orders",  "aggregate": "max", "field": "total" }
    ],

    "sort": {
        "created_at": "desc",
        "name": "asc",
        "raw:FIELD(status,'active','pending','inactive')": "asc"
    },

    "group": ["status", "role"],

    "page": 1,
    "limit": 20
}
```

> **GET request tip:** JSON-encode each parameter value individually in the query string — do not encode the whole object as one string.
> ```
> GET /api/users
>   ?filter={"AND":[{"field":"status","op":"=","value":"active"}]}
>   &sort={"created_at":"desc"}
>   &include=[{"name":"orders","aggregate":"count"}]
>   &page=1
>   &limit=20
> ```

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

> **Soft Deletes:** Any filter condition targeting `deleted_at` — regardless of operator — automatically applies `->withTrashed()` globally, so soft-deleted records are included in the result set. This means operators like `=`, `between`, or `not_null` on `deleted_at` all trigger the same behaviour.

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

Compare two columns using `field|<operator>` syntax. Supported operators: `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`.

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

Omitting the sub-operator defaults to `=`:

```php
['field' => 'orders', 'op' => 'has', 'value' => 1]
// ->has('orders', '=', 1)
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

    ['name' => 'orders', 'aggregate' => 'min', 'field' => 'total'],
    // $user->orders_min_total

    ['name' => 'orders', 'aggregate' => 'max', 'field' => 'total'],
    // $user->orders_max_total
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

Validates: `select` (JSON), `filter` (JSON), `include` (JSON), `sort` (JSON), `page` (integer), `limit` (integer, max from config). The `group` parameter is accepted but not formally validated by this class.

**`GetResourceRequest`** — extends `GetCollectionRequest`, validates only `select` and `include` (suitable for single-resource endpoints).

Both expose a `->filters()` method returning the parsed array, ready to pass directly to `Qubuilder::make()`.

```php
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;

public function index(ListUsersRequest $request)
{
    $builder = User::query()->where('tenant_id', auth()->user()->tenant_id);

    return Qubuilder::make($request->filters(), $builder)
        ->query()
        ->paginate();
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
