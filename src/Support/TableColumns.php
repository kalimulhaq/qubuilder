<?php

namespace Kalimulhaq\Qubuilder\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use WeakMap;

/**
 * Reads the column names of a table from the database schema, once per table and connection.
 *
 * Model metadata (`$fillable`, `$casts`) is not a schema: apps leave real columns out of
 * it. The listing is cached per connection object, so a new connection (a new test, a
 * reconnect) reads the schema again. Under PHP-FPM the cache lives for one request; in a
 * long-running worker it lives as long as the connection.
 *
 * @internal Used by `ColumnQualifier` and `MorphTypeResolver`; not part of the public API.
 */
class TableColumns
{
    /**
     * Each value is an `array<string, array<int, string>|null>` keyed by table name.
     *
     * @var WeakMap<Connection, mixed>|null
     */
    private static ?WeakMap $cache = null;

    /**
     * The lower-cased column names of a table, or null when they cannot be read.
     *
     * An empty listing (no such table) or a failed read returns null, so callers can fall
     * back to a rule that does not need the schema.
     *
     * @return array<int, string>|null
     */
    public static function of(Connection $connection, string $table): ?array
    {
        if (self::$cache === null) {
            /** @var WeakMap<Connection, mixed> $map */
            $map = new WeakMap;
            self::$cache = $map;
        }

        /** @var array<string, array<int, string>|null> $tables */
        $tables = self::$cache[$connection] ?? [];

        if (! array_key_exists($table, $tables)) {
            try {
                $listing = $connection->getSchemaBuilder()->getColumnListing($table);
                $tables[$table] = $listing === [] ? null : array_map(fn (string $name) => Str::lower($name), $listing);
            } catch (\Throwable) {
                $tables[$table] = null;
            }

            self::$cache->offsetSet($connection, $tables);
        }

        return $tables[$table];
    }

    /**
     * Whether the table has the column: true or false when the schema is known, null when not.
     */
    public static function has(Connection $connection, string $table, string $column): ?bool
    {
        $columns = self::of($connection, $table);

        return $columns === null ? null : in_array(Str::lower($column), $columns, true);
    }
}
