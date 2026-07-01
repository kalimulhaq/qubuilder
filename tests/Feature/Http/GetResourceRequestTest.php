<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature\Http;

use Kalimulhaq\Qubuilder\Tests\TestCase;

class GetResourceRequestTest extends TestCase
{
    public function test_validates_and_returns_only_select_and_include(): void
    {
        $response = $this->postJson('/test/resource', [
            'select' => ['id', 'name'],
            'include' => [['name' => 'profile']],
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'select' => ['id', 'name'],
            'include' => [['name' => 'profile']],
        ]);
    }

    public function test_ignores_filter_sort_page_limit(): void
    {
        // These params are neither validated nor returned for a resource request,
        // so an otherwise-invalid filter / page does not cause a 422.
        $response = $this->postJson('/test/resource', [
            'select' => ['id'],
            'filter' => [['field' => 'status', 'op' => 'bogus', 'value' => 'x']],
            'sort' => ['name' => 'sideways'],
            'page' => 'not-an-int',
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('filter');
        $response->assertJsonMissingPath('sort');
        $response->assertJsonMissingPath('page');
        $response->assertJsonMissingPath('limit');
    }

    public function test_validates_include(): void
    {
        $this->postJson('/test/resource', [
            'include' => [['aggregate' => 'count']], // missing name
        ])->assertStatus(422)->assertJsonValidationErrors('include');
    }

    public function test_validates_select(): void
    {
        $this->postJson('/test/resource', [
            'select' => ['id', 123],
        ])->assertStatus(422)->assertJsonValidationErrors('select');
    }
}
