<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Filters;

use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Support\Filters\Group;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class GroupTest extends TestCase
{
    public function test_applies_group_by(): void
    {
        $sql = Qubuilder::make([
            'select' => ['status', 'role'],
            'group'  => ['status', 'role'],
        ], User::class)->query()->toSql();

        $this->assertStringContainsString('group by "status", "role"', $sql);
    }

    public function test_no_group_by_when_empty(): void
    {
        $sql = Qubuilder::make(['group' => []], User::class)->query()->toSql();

        $this->assertStringNotContainsString('group by', $sql);
    }

    public function test_is_stringable_and_arrayable(): void
    {
        $group = new Group(['status', '', 'role']);

        $this->assertSame(['status', 'role'], $group->toArray());
        $this->assertSame('status,role', (string) $group);
    }
}
