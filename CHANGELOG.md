# Changelog

All notable changes to `qubuilder` will be documented in this file.

## [1.3.3] — 2026-10-26

### Fixed
- A dotted relation path in a `has` / `doesnthave` filter (`orders.items`, `listable.community`) is compiled one segment at a time. A `MorphTo` at any depth of the path is limited to the morph types that define the nested relation, through the `{relation}Map()` keys or the morph map. Before, the path went to Laravel's nested `whereHas`, which ran the inner relation on every stored morph type and threw `Call to undefined method` on a type without it. (#6)
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
