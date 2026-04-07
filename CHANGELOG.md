# Changelog

All notable changes to `qubuilder` will be documented in this file.

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
