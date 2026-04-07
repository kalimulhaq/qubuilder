<?php

namespace Kalimulhaq\Qubuilder\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Internal utility methods used by the Qubuilder pipeline.
 */
class Helper
{
    /**
     * Resolve the configured HTTP parameter name for a given internal key.
     *
     * Reads `qubuilder.params.{name}` from config. Falls back to `$name` itself
     * when the config value is null or empty.
     *
     * @param  string  $name  Internal key (e.g. `'select'`, `'filter'`, `'sort'`).
     * @return string         The actual HTTP request parameter name to read.
     */
    public static function param(string $name): string
    {
        $paramName = config("qubuilder.params.$name", $name);

        return ! empty($paramName) ? $paramName : $name;
    }

    /**
     * Coerce a JSON string or iterable value into an array.
     *
     * Returns an empty array when the input is neither iterable nor valid JSON.
     *
     * @param  mixed     $input        A JSON-encoded string or any iterable.
     * @param  bool|null $associative  Decode JSON objects as associative arrays (default: true).
     * @param  int       $depth        Maximum JSON decoding depth (default: 512).
     * @param  int       $flags        JSON decoding flags (default: 0).
     * @return array
     */
    public static function inputAsArray(mixed $input, ?bool $associative = true, int $depth = 512, int $flags = 0): array
    {

        if (is_iterable($input)) {
            return (array) $input;
        }

        $output = json_decode($input, $associative, $depth, $flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $output;
    }

    /**
     * Extract and normalise all Qubuilder filter parameters from an HTTP request.
     *
     * Falls back to `request()` when `$req` is null. The `limit` value is
     * clamped between 1 and `maxLimit()`.
     *
     * @param  Request|null  $req
     * @return array{select: array, filter: array, include: array, sort: array, page: int, limit: int}
     */
    public static function input(?Request $req = null): array
    {
        if (! $req instanceof Request) {
            $req = request();
        }

        $limit = $req->integer(self::param('limit'), config('qubuilder.limit.default', 15));
        $limit = $limit > 0 && $limit <= self::maxLimit() ? $limit : self::maxLimit();

        $return = [
            'select' => self::inputAsArray($req->input(self::param('select'))),
            'filter' => self::inputAsArray($req->input(self::param('filter'))),
            'include' => self::inputAsArray($req->input(self::param('include'))),
            'sort' => self::inputAsArray($req->input(self::param('sort'))),
            'page' => $req->integer(self::param('page'), 1),
            'limit' => $limit,
        ];

        return $return;
    }

    /**
     * Get the configured maximum records-per-page limit.
     *
     * Reads `qubuilder.limit.max` from config (default: 50).
     *
     * @return int
     */
    public static function maxLimit(): int
    {
        $limit_max = config('qubuilder.limit.max', 50);

        return ! empty($limit_max) ? $limit_max : 50;
    }

    /**
     * Extract the `select` array from a filters source array.
     *
     * @param  array  $source  A filters array (e.g. from `Helper::input()`).
     * @return array
     */
    public static function select(array $source): array
    {
        return Arr::get($source, self::param('select'), []);
    }

    /**
     * Extract the `include` array from a filters source array.
     *
     * @param  array  $source    A filters array (e.g. from `Helper::input()`).
     * @param  bool   $nameOnly  When true, returns only the `name` values as a flat array.
     * @return array
     */
    public static function include(array $source, bool $nameOnly = false): array
    {
        if ($nameOnly) {
            return Arr::pluck(Arr::get($source, self::param('include'), []), 'name');
        }

        return Arr::get($source, self::param('include'), []);
    }

    /**
     * Extract the `sort` array from a filters source array.
     *
     * @param  array  $source  A filters array (e.g. from `Helper::input()`).
     * @return array<string, string>
     */
    public static function sort(array $source): array
    {
        return Arr::get($source, self::param('sort'), []);
    }

    /**
     * Resolve the return type name of a given class method via reflection.
     *
     * Returns `null` if the method does not exist, `'void'` if no return type
     * is declared, or the fully-qualified class name of the return type.
     *
     * @param  string  $class   Fully-qualified class name.
     * @param  string  $method  Method name to inspect.
     * @return string|null
     */
    public static function getReturnTypes(string $class, string $method): ?string
    {
        $reflection = new ReflectionClass($class); // Reflect the class

        // Check if the method exists in the class
        if (! $reflection->hasMethod($method)) {
            // throw new \InvalidArgumentException("Method {$method} not found in class {$class}");
            return null;
        }

        $reflectionMethod = $reflection->getMethod($method); // Get the method reflection
        $returnType = $reflectionMethod->getReturnType(); // Get the return type

        // Check if the method has a return type and it's a named type
        if ($returnType instanceof ReflectionNamedType) {
            return $returnType->getName();
        }

        // If no return type is defined
        return 'void';
    }
}
