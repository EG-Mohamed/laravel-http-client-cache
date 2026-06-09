<?php

namespace MohamedSaid\HttpClientCache\Tests;

use MohamedSaid\HttpClientCache\HttpClientCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            HttpClientCacheServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');
    }
}
