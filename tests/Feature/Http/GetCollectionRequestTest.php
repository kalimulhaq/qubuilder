<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Http;

use Kalimulhaq\Qubuilder\Tests\TestCase;

class GetCollectionRequestTest extends TestCase
{
    public function test_valid_request_passes_and_normalises_filters(): void
    {
        $response = $this->postJson('/test/collection', [
            'select' => ['id', 'name'],
            'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'active']]],
            'include' => [['name' => 'orders']],
            'sort' => ['name' => 'asc'],
            'group' => ['id', 'name'],
            'page' => 2,
            'limit' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('select', ['id', 'name']);
        $response->assertJsonPath('include', [['name' => 'orders']]);
        $response->assertJsonPath('page', 2);
        $response->assertJsonPath('limit', 10);
    }

    public function test_accepts_json_string_parameters(): void
    {
        $response = $this->getJson('/test/collection?filter='.urlencode('{"field":"status","op":"=","value":"active"}').'&limit=5');

        $response->assertOk();
        $response->assertJsonPath('limit', 5);
        $response->assertJsonPath('filter.field', 'status');
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $this->postJson('/test/collection', [
            'filter' => [['field' => 'status', 'op' => 'bogus', 'value' => 'x']],
        ])->assertStatus(422)->assertJsonValidationErrors('filter');
    }

    public function test_invalid_include_is_rejected(): void
    {
        $this->postJson('/test/collection', [
            'include' => [['aggregate' => 'count']], // missing name
        ])->assertStatus(422)->assertJsonValidationErrors('include');
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $this->postJson('/test/collection', [
            'sort' => ['name' => 'sideways'],
        ])->assertStatus(422)->assertJsonValidationErrors('sort');
    }

    public function test_invalid_select_is_rejected(): void
    {
        $this->postJson('/test/collection', [
            'select' => ['id', 123],
        ])->assertStatus(422)->assertJsonValidationErrors('select');
    }

    public function test_invalid_group_is_rejected(): void
    {
        $this->postJson('/test/collection', [
            'group' => ['id', ['nested']],
        ])->assertStatus(422)->assertJsonValidationErrors('group');
    }

    public function test_page_must_be_a_positive_integer(): void
    {
        $this->postJson('/test/collection', ['page' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('page');
    }

    public function test_limit_is_capped_at_max(): void
    {
        $this->postJson('/test/collection', ['limit' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('limit');
    }

    public function test_select_must_be_subset_of_group(): void
    {
        $this->postJson('/test/collection', [
            'select' => ['id', 'name'],
            'group' => ['id'],
        ])->assertStatus(422)->assertJsonValidationErrors('select');
    }

    public function test_empty_request_is_valid(): void
    {
        $this->postJson('/test/collection', [])
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('limit', 15);
    }

    // ── QUERY method ─────────────────────────────────────────────────────────
    // A QUERY request carries its parameters in the body, like POST. These tests send a JSON
    // body or a query string, which every supported Laravel and Symfony version reads. A
    // form-encoded QUERY body is only parsed by Symfony 7.4 and newer, so it is not tested.

    public function test_query_method_reads_a_json_body(): void
    {
        $response = $this->json('QUERY', '/test/collection', [
            'select' => ['id', 'name'],
            'filter' => ['AND' => [['field' => 'status', 'op' => '=', 'value' => 'active']]],
            'sort' => ['name' => 'asc'],
            'page' => 2,
            'limit' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('select', ['id', 'name']);
        $response->assertJsonPath('filter.AND.0.field', 'status');
        $response->assertJsonPath('sort', ['name' => 'asc']);
        $response->assertJsonPath('page', 2);
        $response->assertJsonPath('limit', 10);
    }

    public function test_query_method_rejects_an_invalid_json_body(): void
    {
        $this->json('QUERY', '/test/collection', [
            'sort' => ['name' => 'sideways'],
        ])->assertStatus(422)->assertJsonValidationErrors('sort');
    }

    public function test_query_method_reads_query_string_parameters(): void
    {
        $response = $this->json('QUERY', '/test/collection?limit=7&filter='.urlencode('{"field":"status","op":"=","value":"active"}'));

        $response->assertOk();
        $response->assertJsonPath('limit', 7);
        $response->assertJsonPath('filter.field', 'status');
    }
}
