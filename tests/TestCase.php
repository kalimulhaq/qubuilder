<?php

namespace Kalimulhaq\Qubuilder\Tests;

use Kalimulhaq\Qubuilder\QubuilderServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            QubuilderServiceProvider::class,
        ];
    }
}
