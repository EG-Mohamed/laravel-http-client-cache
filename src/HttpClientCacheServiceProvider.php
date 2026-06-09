<?php

namespace MohamedSaid\HttpClientCache;

use DateInterval;
use DateTimeInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MohamedSaid\HttpClientCache\Support\CacheState;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use UnitEnum;

class HttpClientCacheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('http-client-cache')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        $this->registerMacros();
    }

    protected function registerMacros(): void
    {
        Factory::macro('cache', function (
            string $key,
            DateTimeInterface|DateInterval|int|array|null $ttl = null,
            array|string|UnitEnum|null $methods = null,
        ): CachedPendingRequest {
            /** @var Factory $this */
            return new CachedPendingRequest(
                $this->createPendingRequest(),
                CacheOptions::make($key, $ttl, $methods),
            );
        });

        PendingRequest::macro('cache', function (
            string $key,
            DateTimeInterface|DateInterval|int|array|null $ttl = null,
            array|string|UnitEnum|null $methods = null,
        ): CachedPendingRequest {
            /** @var PendingRequest $this */
            return new CachedPendingRequest(
                $this,
                CacheOptions::make($key, $ttl, $methods),
            );
        });

        Response::macro('fromCache', function (): bool {
            /** @var Response $this */
            return CacheState::fromCache($this);
        });
    }
}
