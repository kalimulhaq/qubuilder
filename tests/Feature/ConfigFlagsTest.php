<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature;

use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

/**
 * Covers the `allow_select_all` and `allow_include` config flags.
 *
 * Both default to true (verified by the existing Select/Include suites); these
 * tests exercise the restrictive `false` mode on both the query builder and the
 * FormRequest validation layers.
 */
class ConfigFlagsTest extends TestCase
{
    // ── allow_select_all = false ────────────────────────────────────────────

    public function test_builder_falls_back_to_primary_key_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $sql = Qubuilder::make([], User::class)->query()->toSql();

        $this->assertStringContainsString('select "id"', $sql);
        $this->assertStringNotContainsString('select *', $sql);
    }

    public function test_builder_strips_wildcard_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $sql = Qubuilder::make(['select' => ['*']], User::class)->query()->toSql();

        $this->assertStringContainsString('select "id"', $sql);
        $this->assertStringNotContainsString('*', $sql);
    }

    public function test_builder_keeps_explicit_columns_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $sql = Qubuilder::make(['select' => ['id', 'name']], User::class)->query()->toSql();

        $this->assertStringContainsString('select "id", "name"', $sql);
    }

    public function test_missing_select_is_rejected_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $this->postJson('/test/collection', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('select');
    }

    public function test_wildcard_select_is_rejected_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $this->postJson('/test/collection', ['select' => ['*']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('select');
    }

    public function test_include_without_select_is_rejected_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $this->postJson('/test/collection', [
            'select' => ['id'],
            'include' => [['name' => 'orders']],
        ])->assertStatus(422)->assertJsonValidationErrors('include');
    }

    public function test_aggregate_include_is_exempt_from_select_requirement(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $this->postJson('/test/collection', [
            'select' => ['id'],
            'include' => [['name' => 'orders', 'aggregate' => 'count']],
        ])->assertOk();
    }

    public function test_include_with_explicit_select_passes_when_select_all_disabled(): void
    {
        config()->set('qubuilder.allow_select_all', false);

        $this->postJson('/test/collection', [
            'select' => ['id'],
            'include' => [['name' => 'orders', 'select' => ['id', 'user_id']]],
        ])->assertOk();
    }

    public function test_defaults_are_unaffected_when_select_all_enabled(): void
    {
        // Default (true): omitted select still means SELECT *.
        $sql = Qubuilder::make([], User::class)->query()->toSql();

        $this->assertStringContainsString('select *', $sql);
    }

    // ── allow_include = false ───────────────────────────────────────────────

    public function test_builder_never_eager_loads_when_include_disabled(): void
    {
        config()->set('qubuilder.allow_include', false);

        $builder = Qubuilder::make(['include' => [['name' => 'orders']]], User::class)->query();

        $this->assertSame([], $builder->getEagerLoads());
    }

    public function test_include_is_silently_stripped_from_request_when_disabled(): void
    {
        config()->set('qubuilder.allow_include', false);

        $response = $this->postJson('/test/collection', [
            'select' => ['id'],
            'include' => [['name' => 'orders']],
        ]);

        $response->assertOk();
        $response->assertJsonPath('include', []);
    }

    public function test_malformed_include_is_not_validated_when_disabled(): void
    {
        config()->set('qubuilder.allow_include', false);

        // Missing `name` would normally 422; with includes disabled it is ignored.
        $this->postJson('/test/collection', [
            'select' => ['id'],
            'include' => [['aggregate' => 'count']],
        ])->assertOk();
    }

    public function test_includes_still_load_by_default_when_enabled(): void
    {
        $builder = Qubuilder::make(['include' => [['name' => 'orders']]], User::class)->query();

        $this->assertArrayHasKey('orders', $builder->getEagerLoads());
    }
}
