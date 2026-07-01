<?php

namespace Kalimulhaq\Qubuilder\Tests\Unit\Support;

use Illuminate\Http\Request;
use Kalimulhaq\Qubuilder\Support\Helper;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\Fixtures\ReflectionDummy;
use Kalimulhaq\Qubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class HelperTest extends TestCase
{
    // ── param() ──────────────────────────────────────────────────────────────

    public function test_param_falls_back_to_the_internal_name(): void
    {
        $this->assertSame('filter', Helper::param('filter'));
    }

    public function test_param_uses_configured_name(): void
    {
        config()->set('qubuilder.params.filter', 'q');
        $this->assertSame('q', Helper::param('filter'));
    }

    public function test_param_falls_back_when_config_is_empty(): void
    {
        config()->set('qubuilder.params.filter', null);
        $this->assertSame('filter', Helper::param('filter'));
    }

    // ── inputAsArray() ────────────────────────────────────────────────────────

    public function test_input_as_array_passes_through_iterables(): void
    {
        $this->assertSame(['a', 'b'], Helper::inputAsArray(['a', 'b']));
    }

    public function test_input_as_array_decodes_json_strings(): void
    {
        $this->assertSame(['a' => 1], Helper::inputAsArray('{"a":1}'));
    }

    public function test_input_as_array_returns_empty_on_invalid_json(): void
    {
        $this->assertSame([], Helper::inputAsArray('not json'));
    }

    // ── maxLimit() ────────────────────────────────────────────────────────────

    public function test_max_limit_default(): void
    {
        $this->assertSame(50, Helper::maxLimit());
    }

    public function test_max_limit_from_config(): void
    {
        config()->set('qubuilder.limit.max', 25);
        $this->assertSame(25, Helper::maxLimit());
    }

    public function test_max_limit_falls_back_when_empty(): void
    {
        config()->set('qubuilder.limit.max', null);
        $this->assertSame(50, Helper::maxLimit());
    }

    // ── input() ───────────────────────────────────────────────────────────────

    public function test_input_extracts_and_decodes_all_params(): void
    {
        $req = Request::create('/', 'GET', [
            'select' => '["id","name"]',
            'filter' => '{"AND":[{"field":"status","op":"=","value":"active"}]}',
            'include' => '[{"name":"orders"}]',
            'sort' => '{"name":"asc"}',
            'group' => '["status"]',
            'page' => 3,
            'limit' => 20,
        ]);

        $out = Helper::input($req);

        $this->assertSame(['id', 'name'], $out['select']);
        $this->assertSame(['status'], $out['group']);
        $this->assertSame([['name' => 'orders']], $out['include']);
        $this->assertSame(['name' => 'asc'], $out['sort']);
        $this->assertSame(3, $out['page']);
        $this->assertSame(20, $out['limit']);
        $this->assertSame('active', $out['filter']['AND'][0]['value']);
    }

    public function test_input_defaults_page_to_one_and_limit_to_default(): void
    {
        $out = Helper::input(Request::create('/', 'GET'));

        $this->assertSame(1, $out['page']);
        $this->assertSame(15, $out['limit']); // default 15 (within 1..50)
    }

    #[DataProvider('limitClampProvider')]
    public function test_input_clamps_limit(int $given, int $expected): void
    {
        $out = Helper::input(Request::create('/', 'GET', ['limit' => $given]));

        $this->assertSame($expected, $out['limit']);
    }

    public static function limitClampProvider(): array
    {
        return [
            'zero clamps to max' => [0, 50],
            'negative clamps to max' => [-5, 50],
            'above max clamps to max' => [100, 50],
            'valid passes through' => [20, 20],
            'max boundary passes' => [50, 50],
        ];
    }

    public function test_input_honours_configured_param_names(): void
    {
        config()->set('qubuilder.params.filter', 'q');

        $req = Request::create('/', 'GET', ['q' => '{"field":"x","op":"=","value":1}']);
        $out = Helper::input($req);

        $this->assertSame('x', $out['filter']['field']);
    }

    // ── select/include/sort extractors ─────────────────────────────────────────

    public function test_select_include_sort_extractors(): void
    {
        $source = [
            'select' => ['id'],
            'include' => [['name' => 'orders'], ['name' => 'profile']],
            'sort' => ['name' => 'asc'],
        ];

        $this->assertSame(['id'], Helper::select($source));
        $this->assertSame(['name' => 'asc'], Helper::sort($source));
        $this->assertSame($source['include'], Helper::include($source));
        $this->assertSame(['orders', 'profile'], Helper::include($source, true));
    }

    // ── getReturnTypes() ───────────────────────────────────────────────────────

    public function test_get_return_types_returns_null_for_missing_method(): void
    {
        $this->assertNull(Helper::getReturnTypes(User::class, 'nope'));
    }

    public function test_get_return_types_returns_fqcn(): void
    {
        $this->assertSame(User::class, Helper::getReturnTypes(ReflectionDummy::class, 'typed'));
    }

    public function test_get_return_types_returns_void_when_untyped(): void
    {
        $this->assertSame('void', Helper::getReturnTypes(ReflectionDummy::class, 'untyped'));
    }
}
