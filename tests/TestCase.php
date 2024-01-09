<?php

namespace Tests;

use Laragear\Refine\RefineServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [RefineServiceProvider::class];
    }
}
