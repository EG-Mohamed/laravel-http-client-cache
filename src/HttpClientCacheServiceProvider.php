<?php

namespace MohamedSaid\HttpClientCache;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use MohamedSaid\HttpClientCache\Commands\HttpClientCacheCommand;

class HttpClientCacheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-http-client-cache')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_http_client_cache_table')
            ->hasCommand(HttpClientCacheCommand::class);
    }
}
