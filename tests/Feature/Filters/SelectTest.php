<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Support\Filters\Select;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class SelectTest extends TestCase
{
    public function test_applies_the_requested_columns(): void
    {
        $sql = Qubuilder::make(['select' => ['id', 'name', 'email']], User::class)->query()->toSql();

        $this->assertStringContainsString('select "id", "name", "email"', $sql);
    }

    public function test_defaults_to_star_when_missing(): void
    {
        $sql = Qubuilder::make([], User::class)->query()->toSql();

        $this->assertStringContainsString('select *', $sql);
    }

    public function test_defaults_to_star_when_empty_array(): void
    {
        $sql = Qubuilder::make(['select' => []], User::class)->query()->toSql();

        $this->assertStringContainsString('select *', $sql);
    }

    public function test_filters_out_empty_values(): void
    {
        $select = new Select(['id', '', null, 'name']);

        $this->assertSame(['id', 'name'], $select->toArray());
    }

    public function test_is_arrayable_and_stringable(): void
    {
        $select = new Select(['id', 'name']);

        $this->assertInstanceOf(Arrayable::class, $select);
        $this->assertSame(['id', 'name'], $select->toArray());
        $this->assertSame('id,name', (string) $select);
    }
}
