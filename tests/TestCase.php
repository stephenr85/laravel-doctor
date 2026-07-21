<?php

namespace Schemastud\Doctor\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\Doctor\ServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
