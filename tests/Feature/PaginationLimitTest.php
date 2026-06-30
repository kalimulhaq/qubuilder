<?php

namespace Kalimulhaq\Qubuilder\Tests\Feature;

use Illuminate\Http\Request;
use Kalimulhaq\Qubuilder\Support\Facades\Qubuilder;
use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;
use Kalimulhaq\Qubuilder\Tests\TestCase;

class PaginationLimitTest extends TestCase
{
    private function fromRequest(array $query): \Kalimulhaq\Qubuilder\Qubuilder
    {
        return Qubuilder::makeFromRequest(Request::create('/', 'GET', $query), User::class);
    }

    public function test_limit_clamped_to_max_when_zero(): void
    {
        $this->assertSame(50, $this->fromRequest(['limit' => 0])->limit());
    }

    public function test_limit_clamped_to_max_when_above_max(): void
    {
        $this->assertSame(50, $this->fromRequest(['limit' => 100])->limit());
    }

    public function test_valid_limit_passes_through(): void
    {
        $this->assertSame(20, $this->fromRequest(['limit' => 20])->limit());
    }

    public function test_default_limit_when_absent(): void
    {
        $this->assertSame(15, $this->fromRequest([])->limit());
    }

    public function test_page_accessor(): void
    {
        $this->assertSame(3, $this->fromRequest(['page' => 3])->page());
        $this->assertSame(1, $this->fromRequest([])->page());
    }

    public function test_respects_configured_max(): void
    {
        config()->set('qubuilder.limit.max', 25);

        $this->assertSame(25, $this->fromRequest(['limit' => 100])->limit());
    }
}
