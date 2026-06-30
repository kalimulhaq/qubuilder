<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class SortTest extends TestCase
{
    public function test_single_sort(): void
    {
        $sql = Qubuilder::make(['sort' => ['name' => 'asc']], User::class)->query()->toSql();

        $this->assertStringContainsString('order by "name" asc', $sql);
    }

    public function test_multiple_sorts_in_order(): void
    {
        $sql = Qubuilder::make(['sort' => ['created_at' => 'desc', 'name' => 'asc']], User::class)->query()->toSql();

        $this->assertStringContainsString('order by "created_at" desc, "name" asc', $sql);
    }

    public function test_invalid_direction_defaults_to_asc(): void
    {
        $sql = Qubuilder::make(['sort' => ['name' => 'sideways']], User::class)->query()->toSql();

        $this->assertStringContainsString('order by "name" asc', $sql);
    }

    public function test_direction_is_case_insensitive(): void
    {
        $sql = Qubuilder::make(['sort' => ['name' => 'DESC']], User::class)->query()->toSql();

        $this->assertStringContainsString('order by "name" desc', $sql);
    }

    public function test_raw_sort_expression(): void
    {
        $sql = Qubuilder::make([
            'sort' => ["raw:FIELD(status,'active','pending')" => 'asc'],
        ], User::class)->query()->toSql();

        $this->assertStringContainsString("order by FIELD(status,'active','pending') asc", $sql);
    }

    public function test_no_order_by_when_empty(): void
    {
        $sql = Qubuilder::make(['sort' => []], User::class)->query()->toSql();

        $this->assertStringNotContainsString('order by', $sql);
    }
}
