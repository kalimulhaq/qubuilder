# Changelog

All notable changes to `qubuilder` will be documented in this file.

## [1.4.0] — 2026-10-28

### Added
- A `has` / `doesnthave` filter on a `MorphTo` is narrowed by an `=` or `in` condition on the relation's type column (`bookmarkable_type = Article`, bare or table-qualified) in the same AND group or an enclosing one. An OR group of such conditions on that column counts as one `in`; a type condition that is an OR alternative to the `has` does not narrow it. Values match the morph alias or class name case-insensitively, a value that matches no known type turns the narrowing off, and a class name from the request is never used to build a sub-query. (#9)
- `Where` and `WhereClause` take an optional third constructor parameter, `$context`, that carries those column values down the filter tree. It defaults to empty, so existing calls are unchanged. (#9)

### Changed
- Without a `{relation}Map()`, a `has` / `doesnthave` on a `MorphTo` searches only the morph map types that rows actually store, found with one `SELECT DISTINCT` on the type column (the query Laravel's `whereHasMorph('*')` runs). Results are the same; the query has fewer sub-queries. Define a `{relation}Map()` to skip the extra query. (#9)

### Fixed
- A `has` / `doesnthave` on a `MorphTo` no longer builds a sub-query for a morph type that cannot run the sub-filter. Each type is checked against the whole sub-filter, at every nesting level: nested relations must exist on the model they run on, and every column must exist in the related table, the `BelongsToMany` pivot or the through relation's intermediate table. Before, only the first level was checked, so two types that share a relation name but reach different tables made MySQL fail with "Unknown column". (#9)
- The column check reads the database schema, once per table and connection, instead of `$fillable`. Before, a real column left out of `$fillable` (such as `company_id`) dropped the type, and its rows silently went missing. (#9)
- A `has` on a `MorphTo` for which no type is eligible matches no rows, and a `doesnthave` matches every row, also when an OR condition follows it. Before, the filter was skipped, so a `has` matched every row. (#9)
- An `any` / `all` / `none` condition inside a `has` sub-filter on a `MorphTo` no longer throws a `TypeError`, and a `raw` condition there is no longer checked as a column name. (#9)
- A `has` / `doesnthave` sub-filter and an aggregate include's `filter` on a relation whose query joins another table (`BelongsToMany`, `HasManyThrough`, `HasOneThrough`) qualify their column names, as includes already did. Before, a bare `id`, `created_at` or `updated_at` failed with "Column 'id' in where clause is ambiguous" when the pivot or intermediate table had the same column. (#10)
- An include on a through relation can filter and sort on a column of the intermediate table again, and an include can filter on a pivot column not declared with `withPivot()`. Since 1.3.3 these columns were qualified with the related table and the query failed with "no such column". Column qualification now reads the database schema, once per table and connection, to pick the table that has the column. (#10)

## [1.3.3] — 2026-10-26

### Fixed
- A dotted relation path in a `has` / `doesnthave` filter (`orders.items`, `commentable.author`) is compiled one segment at a time. A `MorphTo` at any depth of the path is limited to the morph types that define the nested relation, through the `{relation}Map()` keys or the morph map. Before, the path went to Laravel's nested `whereHas`, which ran the inner relation on every stored morph type and threw `Call to undefined method` on a type without it. (#6)
- Inside an include on a relation whose query joins another table (`BelongsToMany`, `HasManyThrough`, `HasOneThrough`), bare column names in `select`, `sort`, `group` and `filter` are qualified with the related table, or with the pivot table for a `BelongsToMany` pivot key or `withPivot()` column. Before, `select: ["id", "name"]` on such an include failed with "Column 'id' in field list is ambiguous", and an include with a `sort` or `filter` but no `select` returned the pivot or intermediate row's `id`, `created_at` and `updated_at` as the related model's. Relations without joins are unchanged. (#7)
- With `allow_select_all` off, a table-qualified wildcard (`users.*`) is stripped like a bare `*`, and the primary-key fallback is qualified when the query has joins. (#7)
- The includes code passes PHPStan on current Laravel; CI resolves fresh dependencies and had been red on `phpstan`.

### Other
- Documented and tested support for the HTTP `QUERY` method (RFC 10008). `GetCollectionRequest`, `GetResourceRequest` and `Qubuilder::makeFromRequest()` already read the JSON body and query string of a `QUERY` request. The README recommends `QUERY` for list endpoints and explains route registration and that a form-encoded body needs Symfony 7.4 or newer.
- README: a banner at the top, and an opening that says what each key does and what is supported (PHP, Laravel, input, relations, limits).
- CI: the Composer cache path is fixed and vendor deprecations on the oldest dependencies are ignored.
- A pull request template and a Pint pre-commit hook for contributors.

## [1.1.0] — 2026-04-07

### Added
- Laravel 13 compatibility
- Extended `illuminate/*` constraints to `^13.0`
- Extended `orchestra/testbench` dev constraint to `^11.0`
- CI matrix now tests against Laravel 11, 12, and 13

## [1.0.0] — 2026-04-07

### Added
- Core `Qubuilder` class with `make()`, `makeFromArray()`, and `makeFromRequest()` factory methods
- Full filter pipeline: `Select`, `Where`, `Sorts`, and `Includes` filter classes
- Recursive AND/OR WHERE clause builder via `WhereClause`
- Eager-load support with sub-filters, nested includes, and aggregate variants (`count`, `avg`, `sum`, `min`, `max`)
- Raw expression support in sort (`raw:` prefix) and WHERE clauses
- `json_contains` / `json_not_contains` operator support
- GROUP BY support via the `group` parameter
- Automatic `withTrashed()` when filtering on `deleted_at`
- `GetCollectionRequest` and `GetResourceRequest` Laravel form request base classes
- `ValidateJson` custom validation rule accepting JSON strings or pre-decoded arrays
- Facade `Kalimulhaq\Qubuilder\Support\Facades\Qubuilder` with full IDE method discovery via `@method` annotations
- Config file for parameter name aliases and per-page limit defaults
