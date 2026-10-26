# Changelog

All notable changes to `qubuilder` will be documented in this file.

## [Unreleased]

### Added
- Documented and tested support for the HTTP `QUERY` method (RFC 10008). `GetCollectionRequest`, `GetResourceRequest` and `Qubuilder::makeFromRequest()` already read the JSON body and query string of a `QUERY` request. The README now recommends `QUERY` for list endpoints, and explains route registration and that a form-encoded body needs Symfony 7.4 or newer.

### Changed
- README: a banner at the top, and an opening that says what each key does and what is supported (PHP, Laravel, input, relations, limits).

### Fixed
- Inside an include on a relation whose query joins another table (`BelongsToMany`, `HasManyThrough`, `HasOneThrough`), bare column names in `select`, `sort`, `group` and `filter` are now qualified with the related table, or with the pivot table for a `BelongsToMany` pivot key or `withPivot()` column. Before, `select: ["id", "name"]` on such an include failed with "Column 'id' in field list is ambiguous", and an include with a `sort` or `filter` but no `select` returned the pivot or intermediate row's `id`, `created_at` and `updated_at` as the related model's. Relations without joins are unchanged.
- With `allow_select_all` off, a table-qualified wildcard (`users.*`) is stripped like a bare `*`, and the primary-key fallback is qualified when the query has joins.

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
