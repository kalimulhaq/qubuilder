<?php

namespace Kalimulhaq\Qubuilder\Tests\Fixtures;

use Kalimulhaq\Qubuilder\Tests\Fixtures\Models\User;

/**
 * Fixture for exercising Helper::getReturnTypes() reflection branches.
 */
class ReflectionDummy
{
    public function typed(): User
    {
        return new User;
    }

    public function untyped()
    {
        return null;
    }
}
