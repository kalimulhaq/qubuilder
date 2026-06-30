<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature;

use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    private function seedUsers(): void
    {
        User::create(['name' => 'Live A']);
        User::create(['name' => 'Live B']);
        $trashed = User::create(['name' => 'Gone']);
        $trashed->delete(); // soft delete
    }

    public function test_normal_query_excludes_trashed(): void
    {
        $this->seedUsers();

        $builder = Qubuilder::make([], User::class)->query();

        $this->assertStringContainsString('"deleted_at" is null', $builder->toSql());
        $this->assertCount(2, $builder->get());
    }

    public function test_filter_on_deleted_at_applies_withTrashed(): void
    {
        $this->seedUsers();

        $builder = Qubuilder::make([
            'filter' => [['field' => 'deleted_at', 'op' => 'not_null']],
        ], User::class)->query();

        // withTrashed removes the automatic soft-delete scope...
        $this->assertStringNotContainsString('"deleted_at" is null', $builder->toSql());

        // ...and the explicit condition returns only the trashed row.
        $results = $builder->get();
        $this->assertCount(1, $results);
        $this->assertSame('Gone', $results->first()->name);
    }

    public function test_any_operator_on_deleted_at_triggers_withTrashed(): void
    {
        $this->seedUsers();

        // Even a plain equality / null check on deleted_at pulls in trashed rows.
        $builder = Qubuilder::make([
            'filter' => [['field' => 'deleted_at', 'op' => 'null']],
        ], User::class)->query();

        $this->assertStringNotContainsString('and "qubuilder_users"."deleted_at" is null', $builder->toSql());
        // op=null keeps only rows where deleted_at IS NULL, i.e. the two live rows.
        $this->assertCount(2, $builder->get());
    }
}
